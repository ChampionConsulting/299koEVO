<?php

namespace Core\Auth\Controllers;

use Core\Controllers\AdminController;
use Core\Responses\AdminResponse;
use Core\Auth\User;
use Core\Auth\Permissions;
use Core\Auth\RegistrationRequest;

/**
 * @copyright (C) 2024, 299Ko
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GPLv3
 * @author Maxence Cauderlier <mx.koder@gmail.com>
 *
 * @package 299Ko https://github.com/299Ko/299ko
 */
defined('ROOT') or exit('Access denied!');

class UsersAdminController extends AdminController {

    public function __construct() {
        parent::__construct();
        $evaluator = \Core\Navigation\AccessPolicyEvaluator::getInstance();
        $ctx = new \Core\Navigation\ConditionContext($this->user);
        if (!$evaluator->canAccess(new \Core\Navigation\GenericResource('core:user:manage'), $ctx)) {
            \Utils\Show::msg(\Core\Lang::get('permissions.denied'), 'error');
            $this->core->redirect($this->router->generate('admin'));
        }
    }

    public function home() {

        $response = new AdminResponse();
        $tpl = $response->createPluginTemplate('users', 'userslist');

        $users = User::all();
        $groups = $this->core->auth()->getGroups();
        $groupMap = [];
        foreach ($groups as $group) {
            $groupMap[$group->attributes['id'] ?? null] = $group;
        }

        foreach ($users as $user) {
            $user->deleteLink = $this->router->generate("users-delete", ["id" => $user->id , "token" => $this->user->token]);
            $groupId = $user->attributes['group_id'] ?? ($user->attributes['role'] ?? null);
            $group = $groupMap[$groupId] ?? ($groupMap[(int)$groupId] ?? null);
            $user->group_name = $group ? ($group->attributes['name'] ?? '') : '-';
            $user->group_slug = $group ? ($group->attributes['slug'] ?? '') : null;
        }
        
        // Récupérer les demandes d'inscription en attente
        $pendingRegistrations = [];
        $allRegistrations = RegistrationRequest::all();
        foreach ($allRegistrations as $reg) {
            $status = $reg->attributes['status'] ?? '';
            $validationMode = $reg->attributes['validation_mode'] ?? '';
            // Afficher seulement les demandes en attente de validation admin
            if ($status === 'pending' && $validationMode === 'admin') {
                // Préparer les données pour le template (utiliser __get pour accéder aux attributs)
                $regData = [
                    'token' => $reg->token ?? ($reg->attributes[RegistrationRequest::getPrimaryKey()] ?? ''),
                    'username' => $reg->username ?? ($reg->attributes['username'] ?? ''),
                    'email' => $reg->email ?? ($reg->attributes['email'] ?? ''),
                    'created_at' => $reg->created_at ?? ($reg->attributes['created_at'] ?? null),
                ];
                $pendingRegistrations[] = $regData;
            }
        }
        
        $tpl->set('users', $users);
        $tpl->set('groups', $groups);
        $tpl->set('pendingRegistrations', $pendingRegistrations);
        $tpl->set('permissionsDefinitions', Permissions::translated());
        $tpl->set('token', $this->user->token);

        $response->addTemplate($tpl);
        return $response;
    }
    
    public function approveRegistration(string $token)
    {
        /** @var RegistrationRequest|null $registration */
        $registration = RegistrationRequest::findPK($token);
        if ($registration === null) {
            \Utils\Show::msg(\Core\Lang::get('users.registration-invalid-token'), 'error');
            $this->core->redirect($this->router->generate('users-admin-home'));
            return null;
        }
        
        $status = $registration->attributes['status'] ?? '';
        $validationMode = $registration->attributes['validation_mode'] ?? '';
        if ($status !== 'pending' || $validationMode !== 'admin') {
            \Utils\Show::msg(\Core\Lang::get('users.registration-invalid-token'), 'error');
            $this->core->redirect($this->router->generate('users-admin-home'));
            return null;
        }
        
        // Pour la validation admin, on ne vérifie pas l'expiration (expires_at = 0)
        // L'expiration ne s'applique que pour la validation par email
        if ($validationMode === 'email' && $registration->isExpired()) {
            \Utils\Show::msg(\Core\Lang::get('users.registration-invalid-token'), 'error');
            $this->core->redirect($this->router->generate('users-admin-home'));
            return null;
        }
        
        // Vérifier que l'email n'existe pas déjà
        $email = $registration->attributes['email'] ?? '';
        if (User::find('email', $email) !== null) {
            $registration->delete();
            \Utils\Show::msg(\Core\Lang::get('users.registration-email-exists'), 'error');
            $this->core->redirect($this->router->generate('users-admin-home'));
            return null;
        }
        
        // Créer l'utilisateur
        $user = new User();
        $user->username = $registration->attributes['username'] ?? '';
        $user->email = $email;
        $user->pwd = $registration->attributes['pwd'] ?? '';
        $user->attributes['firstname'] = $registration->attributes['firstname'] ?? '';
        $user->attributes['lastname'] = $registration->attributes['lastname'] ?? '';
        $groupSlug = $registration->attributes['group_slug'] ?? null;
        if ($groupSlug && $this->core->auth()->getGroupBySlug($groupSlug) !== null) {
            $user->group_id = $this->core->auth()->getGroupBySlug($groupSlug)->attributes['id'];
        }
        $user->status = 'active';
        $user->permissions = $registration->attributes['permissions'] ?? [];
        $user->save();
        
        // Notification par email à l'utilisateur approuvé
        $this->sendUserApprovalMail($user);
        $this->core->callHook('users:after-registration-approved', ['user' => $user]);

        // Supprimer la demande
        $registration->delete();
        
        \Utils\Show::msg('Demande d\'inscription approuvée. L\'utilisateur a été créé et notifié par email.', 'success');
        $this->core->redirect($this->router->generate('users-admin-home'));
        return null;
    }
    
    public function rejectRegistration(string $token)
    {
        $registration = RegistrationRequest::findPK($token);
        if ($registration === null) {
            \Utils\Show::msg(\Core\Lang::get('users.registration-invalid-token'), 'error');
            $this->core->redirect($this->router->generate('users-admin-home'));
            return null;
        }
        
        $status = $registration->attributes['status'] ?? '';
        $validationMode = $registration->attributes['validation_mode'] ?? '';
        if ($status !== 'pending' || $validationMode !== 'admin') {
            \Utils\Show::msg(\Core\Lang::get('users.registration-invalid-token'), 'error');
            $this->core->redirect($this->router->generate('users-admin-home'));
            return null;
        }
        
        $email = $registration->attributes['email'] ?? '';
        $username = $registration->attributes['username'] ?? '';
        if (!empty($email)) {
            $this->sendUserRejectionMail($email, $username);
        }
        $this->core->callHook('users:registration-rejected', ['email' => $email, 'username' => $username]);

        // Supprimer la demande
        $registration->delete();
        
        \Utils\Show::msg('Demande d\'inscription rejetée et notification envoyée.', 'success');
        $this->core->redirect($this->router->generate('users-admin-home'));
        return null;
    }

    protected function sendUserApprovalMail(User $user): bool
    {
        $siteName = $this->core->getConfigVal('siteName') ?: '299ko EVO';
        $siteUrl = rtrim((string)($this->core->getConfigVal('siteUrl') ?: ''), '/');
        $domain = parse_url($siteUrl, PHP_URL_HOST) ?: 'localhost';
        $from = $siteName . ' <no-reply@' . $domain . '>';
        $reply = $from;
        $to = $user->email;
        $loginUrl = \Utils\Util::urlBuild($this->router->generate('login'));

        $subject = "[{$siteName}] Votre compte a été validé !";
        $msg = "Bonjour {$user->username()},\n\n"
             . "Bonne nouvelle ! Votre demande d'inscription sur {$siteName} a été approuvée par un administrateur.\n\n"
             . "Votre compte est désormais pleinement actif. Vous pouvez vous connecter dès maintenant en cliquant sur le lien ci-dessous :\n"
             . "{$loginUrl}\n\n"
             . "Bienvenue parmi nous !\n\n"
             . "Cordialement,\n"
             . "L'équipe {$siteName}";

        return \Utils\Util::sendEmail($from, $reply, $to, $subject, $msg);
    }

    protected function sendUserRejectionMail(string $email, string $username): bool
    {
        $siteName = $this->core->getConfigVal('siteName') ?: '299ko EVO';
        $siteUrl = rtrim((string)($this->core->getConfigVal('siteUrl') ?: ''), '/');
        $domain = parse_url($siteUrl, PHP_URL_HOST) ?: 'localhost';
        $from = $siteName . ' <no-reply@' . $domain . '>';
        $reply = $from;

        $subject = "[{$siteName}] Information concernant votre demande d'inscription";
        $msg = "Bonjour {$username},\n\n"
             . "Nous vous informons que votre demande d'inscription sur le site {$siteName} n'a pas été retenue par l'équipe d'administration.\n\n"
             . "Cordialement,\n"
             . "L'équipe {$siteName}";

        return \Utils\Util::sendEmail($from, $reply, $email, $subject, $msg);
    }
}