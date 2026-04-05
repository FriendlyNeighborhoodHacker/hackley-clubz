<?php
declare(strict_types=1);
/**
 * POST handler — run the conversation backfill maintenance task.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/UserContext.php';
require_once __DIR__ . '/../lib/ConversationManagement.php';

Application::init();
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/maintenance.php');
}
try {
    csrf_verify();
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/admin/maintenance.php');
}

$ctx = UserContext::getLoggedInUserContext();

try {
    $result = ConversationManagement::backfillClubConversations($ctx);
    Flash::set(
        'success',
        sprintf(
            'Backfill complete — %d club(s) processed, %d General chat(s) created, %d Leadership chat(s) created.',
            $result['clubs_processed'],
            $result['general_created'],
            $result['leadership_created']
        )
    );
} catch (\RuntimeException $e) {
    Flash::set('error', 'Backfill failed: ' . $e->getMessage());
}

redirect('/admin/maintenance.php');
