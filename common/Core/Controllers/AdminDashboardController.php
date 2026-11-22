<?php

namespace Core\Controllers;

use Core\Auth\User;
use Core\Navigation\MenuService;
use Core\Page\Page;
use Core\Plugin\PluginsManager;
use Core\Responses\AdminResponse;

defined('ROOT') or exit('Access denied!');

/**
 * Tableau de bord général obligatoire de l'administration (/admin).
 */
class AdminDashboardController extends AdminController
{
    public function index(): AdminResponse
    {
        $response = new AdminResponse();
        $response->setTitle('<i class="fa-solid fa-gauge-high"></i> Tableau de bord');

        $stats = $this->collectCoreStats();
        $pluginCards = $this->collectPluginStats();
        $recentPages = $this->collectRecentPages();
        $recentUsers = $this->collectRecentUsers();
        $recentMessages = $this->collectRecentMessages();
        $trafficStats = $this->collectTrafficStats();
        $weatherInfo = $this->collectWeatherInfo();
        $systemHealth = $this->collectSystemHealth();

        $extraWidgets = $this->core->callHook('admin:dashboard-widgets', []);
        if (!is_array($extraWidgets)) {
            $extraWidgets = [];
        }

        $quickLinks = $this->buildQuickLinks();

        $hour = (int)date('H');
        $greeting = ($hour >= 5 && $hour < 18) ? 'Bonjour' : 'Bonsoir';
        $currentUser = \Core\Auth\UsersManager::getCurrentUser();
        $adminName = $currentUser ? ($currentUser->username ?? $currentUser->name ?? 'Administrateur') : 'Administrateur';

        $monthsFr = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'
        ];
        $daysFr = [
            0 => 'Dimanche', 1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi',
            4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi'
        ];
        $dayOfWeek = $daysFr[(int)date('w')];
        $dayOfMonth = date('j');
        $monthName = $monthsFr[(int)date('n')];
        $year = date('Y');
        $formattedDate = "{$dayOfWeek} {$dayOfMonth} {$monthName} {$year}";

        $tpl = $response->createPluginTemplate('dashboard', 'index');
        $tpl->set('stats', $stats);
        $tpl->set('pluginCards', $pluginCards);
        $tpl->set('recentPages', $recentPages);
        $tpl->set('recentUsers', $recentUsers);
        $tpl->set('recentMessages', $recentMessages);
        $tpl->set('trafficStats', $trafficStats);
        $tpl->set('weatherInfo', $weatherInfo);
        $tpl->set('systemHealth', $systemHealth);
        $tpl->set('greeting', $greeting);
        $tpl->set('adminName', $adminName);
        $tpl->set('formattedDate', $formattedDate);
        $tpl->set('extraWidgets', $extraWidgets);
        $tpl->set('quickLinks', $quickLinks);
        $tpl->set('siteName', (string)$this->core->getConfigVal('siteName'));
        $tpl->set('siteUrl', (string)$this->core->getConfigVal('siteUrl'));
        $tpl->set('phpVersion', PHP_VERSION);
        $tpl->set('cmsVersion', defined('VERSION') ? VERSION : '0.1.0');
        $tpl->set('storageDriver', $this->resolveStorageLabel());
        $response->addTemplate($tpl);

        return $response;
    }

    private function resolveStorageLabel(): string
    {
        $db = \Core\Database::getInstance();
        $full = $db->getFullConfig();
        $storage = $full['storage'] ?? [];
        $sqlDriver = (string) ($full['database']['driver'] ?? 'json');

        $hasSql = false;
        $hasJson = false;
        foreach ($storage as $val) {
            if ($val === 'json') {
                $hasJson = true;
            } elseif ($val !== '' && $val !== null) {
                $hasSql = true;
            }
        }

        if (!$hasSql) {
            return 'JSON natif';
        }
        if ($hasJson) {
            return ucfirst($sqlDriver) . ' + JSON';
        }
        return ucfirst($sqlDriver);
    }

    private function collectCoreStats(): array
    {
        $pageManager = new Page();
        $publishedPages = 0;
        $draftPages = 0;
        foreach ($pageManager->getItems() as $p) {
            if ($p->targetIs() !== 'page') {
                continue;
            }
            if ($p->getIsHidden()) {
                $draftPages++;
            } else {
                $publishedPages++;
            }
        }

        $menuCounts = MenuService::getInstance()->getLocationCounts();
        $totalMenuItems = array_sum($menuCounts);

        $usersCount = 0;
        try {
            $usersCount = User::queryBuilder()->count();
        } catch (\Throwable $e) {
            $usersCount = 0;
        }

        $activePlugins = 0;
        $totalPlugins = 0;
        foreach ($this->pluginsManager->getPlugins() as $plugin) {
            $totalPlugins++;
            if ($plugin->getConfigVal('activate')) {
                $activePlugins++;
            }
        }

        $pendingRegistrationsCount = 0;
        try {
            foreach (\Core\Auth\RegistrationRequest::all() as $reg) {
                if (($reg->attributes['status'] ?? '') === 'pending' && ($reg->attributes['validation_mode'] ?? '') === 'admin') {
                    $pendingRegistrationsCount++;
                }
            }
        } catch (\Throwable $e) {}

        return [
            'publishedPages'            => $publishedPages,
            'draftPages'                => $draftPages,
            'totalMenuItems'            => $totalMenuItems,
            'usersCount'                => $usersCount,
            'pendingRegistrationsCount' => $pendingRegistrationsCount,
            'activePlugins'             => $activePlugins,
            'totalPlugins'              => $totalPlugins,
        ];
    }

    private function collectRecentPages(): array
    {
        $pageManager = new Page();
        $items = [];
        foreach ($pageManager->getItems() as $p) {
            if ($p->targetIs() !== 'page') {
                continue;
            }
            $items[] = [
                'id'       => $p->getId(),
                'name'     => $p->getName() ?: $p->getMainTitle() ?: 'Sans titre',
                'target'   => $p->getTarget(),
                'isHidden' => (bool)$p->getIsHidden(),
                'isHome'   => (bool)$p->getIsHomepage(),
            ];
        }
        return array_slice(array_reverse($items), 0, 5);
    }

    private function collectRecentUsers(): array
    {
        $users = [];
        try {
            $rows = User::queryBuilder()->limit(5)->get();
            foreach ($rows as $u) {
                $users[] = [
                    'id'       => $u->id ?? 0,
                    'username' => $u->username ?? $u->name ?? $u->email,
                    'email'    => $u->email,
                    'role'     => $u->group ?? 'Membre',
                    'date'     => !empty($u->created_at) ? date('d/m/Y', strtotime($u->created_at)) : '',
                ];
            }
        } catch (\Throwable $e) {
        }
        return $users;
    }

    private function collectRecentMessages(): array
    {
        $messages = [];
        if (!PluginsManager::isActivePlugin('contact')) {
            return $messages;
        }
        try {
            $pdo = \Core\Database::getInstance()->getPdo();
            if ($pdo) {
                $stmt = $pdo->query("SELECT id, name, email, subject, created_at FROM contact_messages ORDER BY id DESC LIMIT 5");
                if ($stmt) {
                    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                        $messages[] = [
                            'id'      => $row['id'],
                            'name'    => $row['name'] ?: 'Visiteur',
                            'email'   => $row['email'],
                            'subject' => $row['subject'] ?: '(Sans objet)',
                            'date'    => !empty($row['created_at']) ? date('d/m H:i', strtotime($row['created_at'])) : '',
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
        }
        return $messages;
    }

    private function collectTrafficStats(): array
    {
        $todayPageviews = 0;
        $todayVisits = 0;
        $todayLogFile = DATA . 'plugin/lightstats/logs/' . date('Y/m/d') . '.jsonl';
        if (file_exists($todayLogFile)) {
            $lines = file($todayLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                $todayPageviews = count($lines);
                $humanIps = [];
                $allIps = [];
                foreach ($lines as $line) {
                    $row = json_decode($line, true);
                    if (!is_array($row)) {
                        continue;
                    }
                    $hash = $row['ipHash'] ?? ($row['ip_hash'] ?? null);
                    if ($hash) {
                        $allIps[$hash] = true;
                        if (empty($row['isBot']) && empty($row['isAdmin'])) {
                            $humanIps[$hash] = true;
                        }
                    }
                }
                $todayVisits = !empty($humanIps) ? count($humanIps) : count($allIps);
            }
        }
        return [
            'todayVisits'    => $todayVisits,
            'todayPageviews' => $todayPageviews,
        ];
    }

    private function collectWeatherInfo(): ?array
    {
        $forecastCache = DATA . 'plugin/meteo/forecast.cache.json';
        if (!file_exists($forecastCache)) {
            return null;
        }
        $cached = json_decode(file_get_contents($forecastCache), true);
        if (empty($cached['data']['current'])) {
            return null;
        }
        $curr = $cached['data']['current'];
        return [
            'temp'     => round($curr['temperature_2m'] ?? 0),
            'city'     => $cached['city'] ?? ($this->core->getConfigVal('siteCity') ?: 'Paris'),
            'humidity' => $curr['relative_humidity_2m'] ?? 0,
            'wind'     => round($curr['wind_speed_10m'] ?? 0),
            'code'     => (int)($curr['weather_code'] ?? 0),
        ];
    }

    private function collectSystemHealth(): array
    {
        $cacheDir = DATA . 'cache';
        $cacheSize = 0;
        if (is_dir($cacheDir)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $cacheSize += $file->getSize();
            }
        }
        $cacheSizeMb = round($cacheSize / (1024 * 1024), 2);

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        return [
            'https'        => $isHttps,
            'cacheSizeMb'  => $cacheSizeMb,
            'activeTheme'  => (string)$this->core->getConfigVal('theme'),
            'maintenance'  => (bool)$this->core->getConfigVal('maintenance_mode'),
            'registration' => (bool)$this->core->getConfigVal('allowRegistrations'),
        ];
    }

    private function collectPluginStats(): array
    {
        $cards = [];
        $needsDb = PluginsManager::isActivePlugin('LiveChat')
            || PluginsManager::isActivePlugin('contact')
            || PluginsManager::isActivePlugin('blog')
            || PluginsManager::isActivePlugin('newsletter')
            || PluginsManager::isActivePlugin('ecommerce')
            || PluginsManager::isActivePlugin('agenda')
            || PluginsManager::isActivePlugin('BugTracker');
        if (!$needsDb) {
            return $cards;
        }
        $pdo = null;
        try {
            $pdo = \Core\Database::getInstance()->getPdo();
        } catch (\Throwable $e) {
            $pdo = null;
        }

        if (PluginsManager::isActivePlugin('LiveChat') && $pdo) {
            try {
                $openCount = (int)$pdo->query("
                    SELECT COUNT(*) FROM vb_livechat_conversations c 
                    WHERE c.status = 'open' 
                      AND EXISTS (SELECT 1 FROM vb_livechat_messages m WHERE m.conversation_id = c.id AND m.sender = 'visitor')
                ")->fetchColumn();
                $unreadCount = (int)$pdo->query("SELECT COALESCE(SUM(unread_admin), 0) FROM vb_livechat_conversations")->fetchColumn();

                if ($unreadCount > 0) {
                    $livechatVal = "{$unreadCount} non lu(s)";
                    $livechatSub = "Message(s) en attente";
                    $livechatAccent = 'warn';
                } elseif ($openCount > 0) {
                    $livechatVal = (string)$openCount;
                    $livechatSub = "{$openCount} conversation(s) active(s)";
                    $livechatAccent = 'blue';
                } else {
                    $livechatVal = "0";
                    $livechatSub = "Aucune conversation active";
                    $livechatAccent = 'blue';
                }

                $cards[] = [
                    'title'  => 'LiveChat Direct',
                    'value'  => $livechatVal,
                    'sub'    => $livechatSub,
                    'icon'   => 'fa-solid fa-comments',
                    'url'    => '/admin/livechat',
                    'accent' => $livechatAccent,
                ];
            } catch (\Throwable $e) {
            }
        }

        if (PluginsManager::isActivePlugin('contact') && $pdo) {
            try {
                $msgCount = (int)$pdo->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn();
                $cards[] = [
                    'title'  => 'Messages Contact',
                    'value'  => (string)$msgCount,
                    'sub'    => 'Demandes enregistrées',
                    'icon'   => 'fa-solid fa-envelope',
                    'url'    => '/admin/contact',
                    'accent' => 'indigo',
                ];
            } catch (\Throwable $e) {
            }
        }

        if (PluginsManager::isActivePlugin('blog')) {
            try {
                $postsCount = 0;
                if ($pdo) {
                    try {
                        $postsCount = (int)$pdo->query('SELECT COUNT(*) FROM blog_posts')->fetchColumn();
                    } catch (\Throwable $e) {
                        $postsCount = 0;
                    }
                }
                if ($postsCount === 0) {
                    $blogFile = DATA . 'plugin/blog/blog.json';
                    if (file_exists($blogFile)) {
                        $raw = json_decode(file_get_contents($blogFile), true);
                        if (is_array($raw)) {
                            $postsCount = count($raw);
                        }
                    }
                }
                $cards[] = [
                    'title'  => 'Articles de Blog',
                    'value'  => (string)$postsCount,
                    'sub'    => 'Publications en ligne',
                    'icon'   => 'fa-regular fa-newspaper',
                    'url'    => '/admin/blog',
                    'accent' => 'green',
                ];
            } catch (\Throwable $e) {
            }
        }

        if ((PluginsManager::isActivePlugin('PoiManager') || PluginsManager::isActivePlugin('SearchMaps')) && $pdo) {
            try {
                $poiCount = (int)$pdo->query('SELECT COUNT(*) FROM poi')->fetchColumn();
                if ($poiCount > 0) {
                    $cards[] = [
                        'title'  => 'Annuaire & POIs',
                        'value'  => number_format($poiCount, 0, ',', ' '),
                        'sub'    => 'Commerces & Lieux référencés',
                        'icon'   => 'fa-solid fa-map-location-dot',
                        'url'    => '/admin/poimanager',
                        'accent' => 'amber',
                    ];
                }
            } catch (\Throwable $e) {
            }
        }

        if (PluginsManager::isActivePlugin('newsletter') && $pdo) {
            try {
                $subsCount = (int)$pdo->query('SELECT COUNT(*) FROM newsletter_subscribers')->fetchColumn();
                $cards[] = [
                    'title'  => 'Newsletter',
                    'value'  => (string)$subsCount,
                    'sub'    => 'Abonnés fidèles',
                    'icon'   => 'fa-solid fa-paper-plane',
                    'url'    => '/admin/newsletter',
                    'accent' => 'purple',
                ];
            } catch (\Throwable $e) {
            }
        }

        if (PluginsManager::isActivePlugin('ecommerce') && $pdo) {
            try {
                $ordersCount = (int)$pdo->query('SELECT COUNT(*) FROM ecommerce_orders')->fetchColumn();
                $cards[] = [
                    'title'  => 'Commandes Boutique',
                    'value'  => (string)$ordersCount,
                    'sub'    => 'Ventes enregistrées',
                    'icon'   => 'fa-solid fa-cart-shopping',
                    'url'    => '/admin/ecommerce',
                    'accent' => 'amber',
                ];
            } catch (\Throwable $e) {
            }
        }

        if (PluginsManager::isActivePlugin('agenda') && $pdo) {
            try {
                $eventsCount = (int)$pdo->query('SELECT COUNT(*) FROM agenda_events WHERE start_date >= CURRENT_DATE')->fetchColumn();
                $cards[] = [
                    'title'  => 'Agenda Événements',
                    'value'  => (string)$eventsCount,
                    'sub'    => 'Événements à venir',
                    'icon'   => 'fa-solid fa-calendar-days',
                    'url'    => '/admin/agenda',
                    'accent' => 'teal',
                ];
            } catch (\Throwable $e) {
            }
        }

        if (PluginsManager::isActivePlugin('BugTracker') && $pdo) {
            try {
                $bugsCount = (int)$pdo->query("SELECT COUNT(*) FROM bug_reports WHERE status != 'closed'")->fetchColumn();
                $cards[] = [
                    'title'  => 'BugTracker',
                    'value'  => (string)$bugsCount,
                    'sub'    => 'Tickets ouverts',
                    'icon'   => 'fa-solid fa-bug',
                    'url'    => '/admin/BugTracker',
                    'accent' => 'rose',
                ];
            } catch (\Throwable $e) {
            }
        }

        return $cards;
    }

    private function buildQuickLinks(): array
    {
        $links = [
            ['label' => '+ Nouvelle page', 'url' => '/admin/page/new', 'icon' => 'fa-solid fa-file-circle-plus'],
            ['label' => 'Médiathèque', 'url' => '/admin/filemanager', 'icon' => 'fa-solid fa-images'],
            ['label' => 'Menus & Navigation', 'url' => '/admin/menus', 'icon' => 'fa-solid fa-bars-staggered'],
            ['label' => 'Paramètres du site', 'url' => '/admin/configmanager', 'icon' => 'fa-solid fa-sliders'],
            ['label' => 'Utilisateurs', 'url' => '/admin/users', 'icon' => 'fa-solid fa-users'],
            ['label' => 'Place de marché', 'url' => '/admin/marketplace', 'icon' => 'fa-solid fa-store'],
        ];

        if (PluginsManager::isActivePlugin('blog')) {
            $links[] = ['label' => '+ Nouvel article', 'url' => '/admin/blog/new', 'icon' => 'fa-solid fa-pen-nib'];
        }
        if (PluginsManager::isActivePlugin('LiveChat')) {
            $links[] = ['label' => 'LiveChat', 'url' => '/admin/livechat', 'icon' => 'fa-solid fa-comments'];
        }

        return $links;
    }
}
