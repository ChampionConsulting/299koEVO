<?php

namespace Core\Workflow\Controllers;

use Core\Controllers\AdminController;
use Core\Responses\AdminResponse;
use Core\Workflow\WorkflowManager;
use Core\Security\Csrf;
use Utils\Show;
use Core\Lang;

/**
 * WorkflowAdminController - Interface de gestion de l'orchestrateur d'automatisation sans code.
 */
class WorkflowAdminController extends AdminController
{
    public function home()
    {
        $response = new AdminResponse();
        $tpl = $response->createPluginTemplate('configmanager', 'workflows');
        $response->setTitle('Orchestrateur de Workflows');

        $manager = WorkflowManager::getInstance();
        $workflows = $manager->getWorkflows();
        $history = $manager->getHistory();
        $availableTriggers = $manager->getAvailableTriggers();

        $tpl->set('workflows', $workflows);
        $tpl->set('history', $history);
        $tpl->set('triggers', $availableTriggers);
        $tpl->set('createUrl', $this->router->generate('workflow-admin-create'));
        $tpl->set('saveUrl', $this->router->generate('workflow-admin-save'));
        $tpl->set('clearHistoryUrl', $this->router->generate('workflow-admin-clear-history'));
        $tpl->set('token', $this->user->token);

        $response->addTemplate($tpl);
        return $response;
    }

    public function edit()
    {
        $response = new AdminResponse();
        $tpl = $response->createPluginTemplate('configmanager', 'workflow_edit');
        
        $manager = WorkflowManager::getInstance();
        $id = $this->params['id'] ?? '';
        $workflow = null;
        if (!empty($id)) {
            $workflow = $manager->getWorkflow($id);
            if (!$workflow) {
                Show::msg("Workflow introuvable : {$id}", 'error');
                $this->core->redirect($this->router->generate('workflow-admin'));
                return;
            }
            $response->setTitle("Modifier le Workflow : {$workflow['name']}");
        } else {
            $response->setTitle('Nouveau Workflow');
            $workflow = [
                'id' => '',
                'name' => '',
                'description' => '',
                'trigger' => 'blog:post_published',
                'is_enabled' => 1,
                'is_async' => 1,
                'conditions' => [],
                'actions' => [
                    [
                        'type' => 'webhook',
                        'url' => '',
                        'method' => 'POST',
                        'body' => '',
                        'headers' => '',
                    ]
                ],
            ];
        }

        $tpl->set('workflow', $workflow);
        $tpl->set('triggers', $manager->getAvailableTriggers());
        $tpl->set('saveUrl', $this->router->generate('workflow-admin-save'));
        $tpl->set('backUrl', $this->router->generate('workflow-admin'));
        $tpl->set('testUrl', $this->router->generate('workflow-admin-test'));
        $tpl->set('token', $this->user->token);

        $response->addTemplate($tpl);
        return $response;
    }

    public function save()
    {
        if (!Csrf::checkToken($_POST['token'] ?? '')) {
            Show::msg('Jeton CSRF expiré ou invalide.', 'error');
            $this->core->redirect($this->router->generate('workflow-admin'));
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $trigger = trim($_POST['trigger'] ?? '');

        if (empty($name) || empty($trigger)) {
            Show::msg('Le nom et l\'événement déclencheur sont obligatoires.', 'error');
            $this->core->redirect($this->router->generate('workflow-admin'));
            return;
        }

        // Décoder les conditions et actions reçues
        $conditions = [];
        if (!empty($_POST['conditions_json'])) {
            $decodedCond = json_decode($_POST['conditions_json'], true);
            if (is_array($decodedCond)) {
                $conditions = $decodedCond;
            }
        }

        $actions = [];
        if (!empty($_POST['actions_json'])) {
            $decodedAct = json_decode($_POST['actions_json'], true);
            if (is_array($decodedAct)) {
                $actions = $decodedAct;
            }
        }

        // Si actions vides, vérifier si un webhook simple a été soumis via formulaire classique
        if (empty($actions) && !empty($_POST['action_webhook_url'])) {
            $actions[] = [
                'type' => 'webhook',
                'url' => trim($_POST['action_webhook_url']),
                'method' => $_POST['action_webhook_method'] ?? 'POST',
                'body' => $_POST['action_webhook_body'] ?? '',
                'headers' => $_POST['action_webhook_headers'] ?? '',
            ];
        }

        $workflowData = [
            'id' => $_POST['id'] ?? '',
            'name' => $name,
            'description' => trim($_POST['description'] ?? ''),
            'trigger' => $trigger,
            'is_enabled' => !empty($_POST['is_enabled']) ? 1 : 0,
            'is_async' => !empty($_POST['is_async']) ? 1 : 0,
            'conditions' => $conditions,
            'actions' => $actions,
        ];

        $manager = WorkflowManager::getInstance();
        $manager->saveWorkflow($workflowData);

        Show::msg("Workflow '{$name}' enregistré avec succès.", 'success');
        $this->core->redirect($this->router->generate('workflow-admin'));
    }

    public function toggle()
    {
        if (!Csrf::checkToken($_POST['token'] ?? '')) {
            Show::msg('Jeton CSRF invalide.', 'error');
            $this->core->redirect($this->router->generate('workflow-admin'));
            return;
        }

        $id = $this->params['id'] ?? '';
        $manager = WorkflowManager::getInstance();
        if ($manager->toggleWorkflow($id)) {
            Show::msg("Statut du workflow mis à jour.", 'success');
        } else {
            Show::msg("Impossible de modifier le workflow : {$id}", 'error');
        }

        $this->core->redirect($this->router->generate('workflow-admin'));
    }

    public function delete()
    {
        if (!Csrf::checkToken($_POST['token'] ?? '')) {
            Show::msg('Jeton CSRF invalide.', 'error');
            $this->core->redirect($this->router->generate('workflow-admin'));
            return;
        }

        $id = $this->params['id'] ?? '';
        $manager = WorkflowManager::getInstance();
        if ($manager->deleteWorkflow($id)) {
            Show::msg("Workflow supprimé avec succès.", 'success');
        } else {
            Show::msg("Erreur lors de la suppression.", 'error');
        }

        $this->core->redirect($this->router->generate('workflow-admin'));
    }

    public function test()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Csrf::checkToken($_POST['token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Jeton CSRF invalide']);
            exit;
        }

        $id = $_POST['id'] ?? ($this->params['id'] ?? '');
        $manager = WorkflowManager::getInstance();
        $workflow = null;

        if (!empty($id)) {
            $workflow = $manager->getWorkflow($id);
        }

        // Si test à la volée depuis le formulaire d'édition
        if (!$workflow && !empty($_POST['workflow_data'])) {
            $workflow = json_decode($_POST['workflow_data'], true);
        }

        if (!$workflow) {
            echo json_encode(['success' => false, 'error' => 'Workflow introuvable']);
            exit;
        }

        // Générer ou récupérer un faux payload réaliste
        $triggers = $manager->getAvailableTriggers();
        $triggerKey = $workflow['trigger'] ?? 'blog:post_published';
        $samplePayload = $triggers[$triggerKey]['sample'] ?? [
            'id' => 999,
            'title' => 'Test Workflow',
            'email' => 'test@example.com',
            'timestamp' => time(),
        ];

        if (!empty($_POST['custom_payload'])) {
            $custom = json_decode($_POST['custom_payload'], true);
            if (is_array($custom)) {
                $samplePayload = $custom;
            }
        }

        try {
            // Évaluation des conditions
            $conditionsPass = $manager->evaluateConditions($workflow['conditions'] ?? [], $samplePayload);
            if (!$conditionsPass) {
                echo json_encode([
                    'success' => false,
                    'conditions_passed' => false,
                    'message' => 'Les conditions du workflow n\'ont pas été satisfaites avec le payload de test.',
                    'sample_payload' => $samplePayload,
                ]);
                exit;
            }

            // Exécution réelle des actions de test
            $execution = $manager->executeActions($workflow, $samplePayload);

            echo json_encode([
                'success' => $execution['success'],
                'conditions_passed' => true,
                'execution' => $execution,
                'sample_payload' => $samplePayload,
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function clearHistory()
    {
        if (!Csrf::checkToken($_POST['token'] ?? '')) {
            Show::msg('Jeton CSRF invalide.', 'error');
            $this->core->redirect($this->router->generate('workflow-admin'));
            return;
        }

        $manager = WorkflowManager::getInstance();
        $manager->clearHistory();

        Show::msg('Historique des exécutions purgé avec succès.', 'success');
        $this->core->redirect($this->router->generate('workflow-admin'));
    }
}

