<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by KISSWAM
   ------------------------------------------------------------------------
   LICENSE: AGPL License 3.0 or (at your option) any later version
   ------------------------------------------------------------------------

   Batch print view: renders the assets collected by the "Print in browser"
   massive action. The action cannot render the page itself (the engine calls
   it once per item type in the same request and rendering exits), so the
   selection is parked in the session and handed over by the action redirect.
 */

use GlpiPlugin\Qrcodelabel\Config;
use GlpiPlugin\Qrcodelabel\Label;
use GlpiPlugin\Qrcodelabel\Printprofile;

// GLPI 10 serves plugin front files as plain PHP scripts: bootstrap GLPI here
// (this also validates the CSRF token of the POST). GLPI 11+ has already
// booted through its Symfony kernel (LegacyFileLoadController) and defined
// GLPI_ROOT.
if (!defined('GLPI_ROOT')) {
   include __DIR__ . '/../../../inc/includes.php';
}

Session::checkLoginUser();
Session::checkRight('plugin_qrcodelabel_label', CREATE);

// Rotation buttons re-post to this page: keep the parked job, only change the
// angle. A job older than 30 minutes is dropped so a stale tab cannot print.
$batch = $_SESSION['qrcodelabel_printbatch'] ?? null;
if (!is_array($batch) || empty($batch['items'])) {
   Session::addMessageAfterRedirect(__('Nothing to print.', 'qrcodelabel'), false, ERROR);
   Html::back();
}
if (time() - (int)($batch['ts'] ?? 0) > 1800) {
   unset($_SESSION['qrcodelabel_printbatch']);
   Session::addMessageAfterRedirect(
      __('The print job has expired, please run the action again.', 'qrcodelabel'),
      false,
      ERROR
   );
   Html::back();
}

$profile = Printprofile::getProfileById((int)($batch['profile_id'] ?? 0))
   ?? Printprofile::getDefault();
if (!$profile) {
   Session::addMessageAfterRedirect(__('No print profile found.', 'qrcodelabel'), false, ERROR);
   Html::back();
}

$config = Config::getConfig();
$copies = Label::sanitizeCopies($batch['copies'] ?? 1);

// Re-validate every id: the session may be older than the current rights, and
// entity visibility must still hold when the page is actually rendered.
$assets = [];
foreach ((array)$batch['items'] as $ref) {
   $itemtype = (string)($ref[0] ?? '');
   $items_id = (int)($ref[1] ?? 0);
   if ($items_id <= 0 || !plugin_qrcodelabel_is_supported_itemtype($itemtype)) {
      continue;
   }
   $item = getItemForItemtype($itemtype);
   if (!($item instanceof CommonDBTM) || !$item->getFromDB($items_id) || !$item->can($items_id, READ)) {
      continue;
   }
   $data = Label::buildAssetData($item);
   for ($c = 0; $c < $copies; $c++) {
      $assets[] = $data;
   }
}

if (empty($assets)) {
   unset($_SESSION['qrcodelabel_printbatch']);
   Session::addMessageAfterRedirect(
      __('No printable asset in the selection.', 'qrcodelabel'),
      false,
      ERROR
   );
   Html::back();
}

$params = Label::buildParams($profile, $config);
if (isset($_POST['rotate'])) {
   $params['print_rotate'] = Label::sanitizeRotation($_POST['rotate']);
}

$ok = Label::renderPrintPage($assets, $params, [
   'action' => Label::getWebPath() . '/front/print.batch.php',
   'fields' => [
      '_glpi_csrf_token' => (string)($_POST['_glpi_csrf_token'] ?? Session::getNewCSRFToken()),
   ],
]);
if (!$ok) {
   Session::addMessageAfterRedirect(__('Label generation failed.', 'qrcodelabel'), false, ERROR);
   Html::back();
}
exit;
