<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by Etienne Gaillard
   ------------------------------------------------------------------------
   LICENSE: AGPL License 3.0 or (at your option) any later version
   ------------------------------------------------------------------------
 */

use GlpiPlugin\Qrcodelabel\Config;
use GlpiPlugin\Qrcodelabel\Fields;
use GlpiPlugin\Qrcodelabel\Printprofile;

// GLPI 10 serves plugin front files as plain PHP scripts: bootstrap GLPI here
// (this also validates the CSRF token of the POST). GLPI 11 has already booted
// through its Symfony kernel (LegacyFileLoadController) and defined GLPI_ROOT.
if (!defined('GLPI_ROOT')) {
   include __DIR__ . '/../../../inc/includes.php';
}

// Gated on the plugin's own right (not core "config") so a profile such as
// Technician can manage QR Code Label settings without full GLPI setup rights.
Session::checkRight('plugin_qrcodelabel_config', UPDATE);
// CSRF is auto-validated by GLPI because $PLUGIN_HOOKS['csrf_compliant'] is set;
// calling Session::checkCSRF() here a second time consumes the single-use token
// and breaks multi-button forms (saveConfig / dropLogo / uploadLogo / pp_*).

$docDir = GLPI_PLUGIN_DOC_DIR . '/qrcodelabel';

// Whitelists for input validation
$validTapeSizes    = ['24mm', '25mm', '36mm', '50mm'];
$validColorModes   = ['bw', 'mono', 'color', 'inverse', 'inverse_mono'];
$validPageSizes    = ['A4', 'A3', 'LETTER', 'LEGAL'];
$validOrientations = ['Portrait', 'Landscape'];
$validOutputFormats = ['pdf', 'png', 'both'];

/**
 * Persist the global configuration.
 *
 * @param array      $post   Posted values.
 * @param array|null $fields Label field list (null = leave the stored list untouched).
 */
$saveConfig = static function (array $post, ?array $fields) use (
   $validTapeSizes,
   $validColorModes,
   $validPageSizes,
   $validOrientations,
   $validOutputFormats
): void {
   global $DB;

   // Per-profile label content: when the editor targets a print profile,
   // only the field list is written (into that profile), the global
   // settings below are left untouched.
   $lfTarget = (string)($post['lf_target'] ?? 'global');
   if (preg_match('/^p(\d+)$/', $lfTarget, $m)
       && $DB->tableExists('glpi_plugin_qrcodelabel_printprofiles')
       && $DB->fieldExists('glpi_plugin_qrcodelabel_printprofiles', 'label_fields')) {
      $pid = (int)$m[1];
      if ($fields !== null) {
         $profile = new Printprofile();
         if ($profile->getFromDB($pid)) {
            $profile->update([
               'id'           => $pid,
               'label_fields' => empty($fields) ? null : Fields::encode($fields),
            ]);
            Session::addMessageAfterRedirect(__('Label content saved to the print profile.', 'qrcodelabel'));
         }
      }
      return;
   }

   // Validate and sanitize inputs against whitelists
   $tapeSize = in_array($post['tape_size'] ?? '', $validTapeSizes, true)
      ? $post['tape_size'] : '36mm';
   $colorMode = in_array($post['color_mode'] ?? '', $validColorModes, true)
      ? $post['color_mode'] : 'bw';
   $showDate = (int)(bool)($post['show_date'] ?? 1);
   $pageSize = in_array($post['page_size'] ?? '', $validPageSizes, true)
      ? $post['page_size'] : 'A4';
   $orientation = in_array($post['orientation'] ?? '', $validOrientations, true)
      ? $post['orientation'] : 'Portrait';
   $ownerText = mb_substr(trim($post['owner_text'] ?? ''), 0, 255);
   $outputFormat = in_array($post['output_format'] ?? '', $validOutputFormats, true)
      ? $post['output_format'] : 'pdf';
   $showLocation = (int)(bool)($post['show_location'] ?? 0);

   $values = [
      'id'            => 1,
      'tape_size'     => $tapeSize,
      'color_mode'    => $colorMode,
      'show_date'     => $showDate,
      'page_size'     => $pageSize,
      'orientation'   => $orientation,
      'owner_text'    => $ownerText,
      'output_format' => $outputFormat,
      'show_location' => $showLocation,
   ];

   // Guard against writing the new column before its migration ran.
   if ($fields !== null && $DB->fieldExists('glpi_plugin_qrcodelabel_configs', 'label_fields')) {
      $values['label_fields'] = empty($fields) ? '' : Fields::encode($fields);
   }

   $config = new Config();
   if ($config->getFromDB(1)) {
      $config->update($values);
   } else {
      // Create if missing (through CommonDBTM so input escaping is handled
      // the same way as for update() on GLPI 10 and 11)
      $config->add($values);
   }
   Session::addMessageAfterRedirect(__('Configuration saved.', 'qrcodelabel'));
};

// ── Profile actions (single-form approach with pp_* fields) ────────────────
$profileAction = $_POST['profile_action'] ?? '';
$profileId     = (int)($_POST['profile_id'] ?? 0);

if ($profileAction === 'update' && $profileId > 0) {
   $pName = mb_substr(trim($_POST["pp_name_{$profileId}"] ?? ''), 0, 100);
   if ($pName !== '') {
      $pTapeSize    = in_array($_POST["pp_tape_{$profileId}"] ?? '', $validTapeSizes, true)
         ? $_POST["pp_tape_{$profileId}"] : '36mm';
      $pColorMode   = in_array($_POST["pp_color_{$profileId}"] ?? '', $validColorModes, true)
         ? $_POST["pp_color_{$profileId}"] : 'bw';
      $pShowDate    = (int)(bool)($_POST["pp_date_{$profileId}"] ?? 1);
      $pPageSize    = in_array($_POST["pp_page_{$profileId}"] ?? '', $validPageSizes, true)
         ? $_POST["pp_page_{$profileId}"] : 'A4';
      $pOrientation = in_array($_POST["pp_orient_{$profileId}"] ?? '', $validOrientations, true)
         ? $_POST["pp_orient_{$profileId}"] : 'Portrait';
      $pIsDefault   = (int)(bool)($_POST["pp_default_{$profileId}"] ?? 0);

      if ($pIsDefault) {
         global $DB;
         $DB->update('glpi_plugin_qrcodelabel_printprofiles', ['is_default' => 0], ['is_default' => 1]);
      }

      $profile = new Printprofile();
      $profile->update([
         'id'          => $profileId,
         'name'        => $pName,
         'tape_size'   => $pTapeSize,
         'color_mode'  => $pColorMode,
         'show_date'   => $pShowDate,
         'page_size'   => $pPageSize,
         'orientation' => $pOrientation,
         'is_default'  => $pIsDefault,
      ]);
      Session::addMessageAfterRedirect(__('Print profile updated.', 'qrcodelabel'));
   }

   // When the label-content editor targets this profile, its Save button also
   // persists the rows currently shown in the editor, so one click saves both
   // the print parameters and the label content.
   global $DB;
   if (($_POST['lf_target'] ?? '') === 'p' . $profileId
       && $DB instanceof \DBmysql
       && $DB->tableExists('glpi_plugin_qrcodelabel_printprofiles')
       && $DB->fieldExists('glpi_plugin_qrcodelabel_printprofiles', 'label_fields')) {
      $fields = Fields::fromPost($_POST);
      $profile = new Printprofile();
      if ($profile->getFromDB($profileId)) {
         $profile->update([
            'id'           => $profileId,
            'label_fields' => empty($fields) ? null : Fields::encode($fields),
         ]);
         Session::addMessageAfterRedirect(__('Label content saved to the print profile.', 'qrcodelabel'));
      }
   }

} else if ($profileAction === 'delete' && $profileId > 0) {
   $profile = new Printprofile();
   if ($profile->getFromDB($profileId)) {
      $profile->delete(['id' => $profileId]);
      Session::addMessageAfterRedirect(__('Print profile deleted.', 'qrcodelabel'));
   }

} else if ($profileAction === 'add') {
   $pName = mb_substr(trim($_POST['pp_name_new'] ?? ''), 0, 100);
   if ($pName !== '') {
      $pTapeSize    = in_array($_POST['pp_tape_new'] ?? '', $validTapeSizes, true)
         ? $_POST['pp_tape_new'] : '36mm';
      $pColorMode   = in_array($_POST['pp_color_new'] ?? '', $validColorModes, true)
         ? $_POST['pp_color_new'] : 'bw';
      $pShowDate    = (int)(bool)($_POST['pp_date_new'] ?? 1);
      $pPageSize    = in_array($_POST['pp_page_new'] ?? '', $validPageSizes, true)
         ? $_POST['pp_page_new'] : 'A4';
      $pOrientation = in_array($_POST['pp_orient_new'] ?? '', $validOrientations, true)
         ? $_POST['pp_orient_new'] : 'Portrait';
      $pIsDefault   = (int)(bool)($_POST['pp_default_new'] ?? 0);

      if ($pIsDefault) {
         global $DB;
         $DB->update('glpi_plugin_qrcodelabel_printprofiles', ['is_default' => 0], ['is_default' => 1]);
      }

      $profile = new Printprofile();
      $profile->add([
         'name'        => $pName,
         'tape_size'   => $pTapeSize,
         'color_mode'  => $pColorMode,
         'show_date'   => $pShowDate,
         'page_size'   => $pPageSize,
         'orientation' => $pOrientation,
         'is_default'  => $pIsDefault,
      ]);
      Session::addMessageAfterRedirect(__('Print profile added.', 'qrcodelabel'));
   } else {
      Session::addMessageAfterRedirect(
         __('Profile name is required.', 'qrcodelabel'),
         false, ERROR
      );
   }

} else if (isset($_POST['dropLogo'])) {
   // Delete logo
   if (is_file($docDir . '/logo.png')) {
      unlink($docDir . '/logo.png');
   }
   Session::addMessageAfterRedirect(__('The logo has been removed.', 'qrcodelabel'));

} else if (isset($_POST['uploadLogo']) && !empty($_FILES['logo']['name'])) {
   // Upload logo — validate file is actually an image
   $tmpFile = $_FILES['logo']['tmp_name'] ?? '';
   if ($tmpFile && is_uploaded_file($tmpFile)) {
      $finfo = @getimagesize($tmpFile);
      // Re-encode every accepted image as a real PNG: the label renderers read
      // logo.png as PNG (a JPEG/GIF stored under that name broke PDF generation),
      // and re-encoding drops any non-image payload appended to the file.
      $img = false;
      if ($finfo && in_array($finfo[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF], true)) {
         $data = @file_get_contents($tmpFile);
         $img  = $data !== false ? @imagecreatefromstring($data) : false;
      }
      if ($img !== false) {
         if (!is_dir($docDir)) {
            mkdir($docDir, 0755, true);
         }
         if (!imageistruecolor($img)) {
            imagepalettetotruecolor($img);
         }
         imagealphablending($img, false);
         imagesavealpha($img, true);
         if (@imagepng($img, $docDir . '/logo.png')) {
            Session::addMessageAfterRedirect(__('Logo uploaded successfully.', 'qrcodelabel'));
         } else {
            Session::addMessageAfterRedirect(__('The logo could not be saved.', 'qrcodelabel'), false, ERROR);
         }
      } else {
         Session::addMessageAfterRedirect(
            __('Invalid file: only PNG, JPEG and GIF images are accepted.', 'qrcodelabel'),
            false, ERROR
         );
      }
   }

} else if (($_POST['field_action'] ?? '') !== '') {
   // Reorder / delete / add a line of the label content. The posted rows are
   // read first, so edits that were not saved yet are kept.
   $fields = Fields::fromPost($_POST);
   $idx    = (int)($_POST['field_idx'] ?? -1);
   $action = (string)$_POST['field_action'];

   if ($action === 'up' && $idx > 0 && $idx < count($fields)) {
      [$fields[$idx - 1], $fields[$idx]] = [$fields[$idx], $fields[$idx - 1]];
   } else if ($action === 'down' && $idx >= 0 && $idx < count($fields) - 1) {
      [$fields[$idx], $fields[$idx + 1]] = [$fields[$idx + 1], $fields[$idx]];
   } else if ($action === 'del' && $idx >= 0 && $idx < count($fields)) {
      array_splice($fields, $idx, 1);
   } else if ($action === 'add') {
      $newKey = (string)($_POST['lf_key_new'] ?? '');
      if (isset(Fields::catalogue()[$newKey]) && count($fields) < Fields::MAX_FIELDS) {
         $fields[] = [
            'key' => $newKey, 'prefix' => '', 'pt' => 6.0,
            'style' => '', 'color' => 'main', 'align' => 'L',
         ];
      }
   } else if ($action === 'reset') {
      // Empty list = "use the defaults derived from the switches below".
      $fields = [];
   }

   $saveConfig($_POST, $fields);

} else if (isset($_POST['saveConfig'])) {
   $saveConfig($_POST, Fields::fromPost($_POST));
}

Html::back();
