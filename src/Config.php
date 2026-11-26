<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by Etienne Gaillard
   ------------------------------------------------------------------------
   LICENSE: AGPL License 3.0 or (at your option) any later version
   ------------------------------------------------------------------------
 */

namespace GlpiPlugin\Qrcodelabel;

use CommonDBTM;
use Dropdown;
use Html;
use Plugin;
use Session;

class Config extends CommonDBTM {

   static $table = 'glpi_plugin_qrcodelabel_configs';

   // $rightname is inherited from CommonGLPI and assigned at runtime in
   // plugin_init_qrcodelabel() — see the note in Label.php (GLPI 12 typed it).

   /** HTML-escape for output in attributes and text nodes. */
   private static function e($value): string {
      return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
   }

   /**
    * Get the singleton config row from the database.
    *
    * @return array
    */
   static function getConfig(): array {
      $defaults = [
         'tape_size'     => '36mm',
         'color_mode'    => 'bw',
         'show_date'     => 1,
         'page_size'     => 'A4',
         'orientation'   => 'Portrait',
         'owner_text'    => '',
         'output_format' => 'pdf',
         'show_location' => 0,
         'label_fields'  => '',
      ];

      $config = new self();
      if ($config->getFromDB(1)) {
         return array_merge($defaults, $config->fields);
      }
      return $defaults;
   }

   /**
    * Show the admin configuration form.
    */
   function showConfigForm(): void {

      $config = self::getConfig();

      echo "<form name='form' method='post' action='"
         . self::e(Label::getWebPath() . '/front/config.form.php') . "'"
         . " enctype='multipart/form-data'>";

      echo "<div class='center'>";
      echo "<table class='tab_cadre_fixe'>";

      // ── Header ────────────────────────────────────────────────────────────
      echo "<tr><th colspan='4'>"
         . __('QR Code Label - Configuration', 'qrcodelabel')
         . "</th></tr>";

      // ── Tape size ─────────────────────────────────────────────────────────
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Default tape size', 'qrcodelabel') . "</td><td>";
      Dropdown::showFromArray('tape_size', [
         '24mm' => '24 mm (Brother)',
         '25mm' => '25 mm',
         '36mm' => '36 mm (Brother)',
         '50mm' => '50 mm',
      ], ['value' => $config['tape_size'], 'width' => '170']);
      echo "</td>";

      // ── Color mode ────────────────────────────────────────────────────────
      echo "<td>" . __('Default color mode', 'qrcodelabel') . "</td><td>";
      Dropdown::showFromArray('color_mode', [
         'bw'           => __('Black & White', 'qrcodelabel'),
         'mono'         => __('Monochrome', 'qrcodelabel'),
         'color'        => __('Color', 'qrcodelabel'),
         'inverse'      => __('Inverse (white on black)', 'qrcodelabel'),
         'inverse_mono' => __('Inverse Mono', 'qrcodelabel'),
      ], ['value' => $config['color_mode'], 'width' => '200']);
      echo "</td></tr>";

      // ── Show date + show location (one row) ────────────────────────────
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Show inventory date', 'qrcodelabel') . "</td><td>";
      Dropdown::showYesNo('show_date', $config['show_date'], -1, ['width' => '100']);
      echo "</td>";
      echo "<td>" . __('Show location', 'qrcodelabel') . "</td><td>";
      Dropdown::showYesNo('show_location', $config['show_location'] ?? 0, -1, ['width' => '100']);
      echo "</td></tr>";

      // ── Page size + Orientation (one row) ──────────────────────────────
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Default page size', 'qrcodelabel') . "</td><td>";
      Dropdown::showFromArray('page_size', [
         'A4' => 'A4', 'A3' => 'A3', 'LETTER' => 'Letter', 'LEGAL' => 'Legal',
      ], ['value' => $config['page_size'], 'width' => '120']);
      echo "</td>";
      echo "<td>" . __('Default orientation', 'qrcodelabel') . "</td><td>";
      Dropdown::showFromArray('orientation', [
         'Portrait'  => __('Portrait', 'qrcodelabel'),
         'Landscape' => __('Landscape', 'qrcodelabel'),
      ], ['value' => $config['orientation'], 'width' => '120']);
      echo "</td></tr>";

      // ── Owner text ────────────────────────────────────────────────────────
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Owner text', 'qrcodelabel') . "</td><td colspan='3'>";
      echo "<input type='text' name='owner_text' value='"
         . self::e(Label::rawText($config['owner_text'] ?? '')) . "'"
         . " size='50' maxlength='255' placeholder='" . self::e(__('e.g. Property of: My Company', 'qrcodelabel')) . "'>";
      echo "</td></tr>";

      // ── Output format (PDF / PNG / both) ───────────────────────────────
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Output format', 'qrcodelabel') . "</td><td colspan='3'>";
      Dropdown::showFromArray('output_format', [
         'pdf'  => __('PDF (label sheet)', 'qrcodelabel'),
         'png'  => __('PNG (Brother P-Touch Cube)', 'qrcodelabel'),
         'both' => __('Both (PDF + PNG)', 'qrcodelabel'),
      ], [
         'value' => $config['output_format'] ?? 'pdf',
         'width' => '300',
      ]);
      echo "</td></tr>";

      // ── Save button ───────────────────────────────────────────────────────
      echo "<tr><td class='tab_bg_1' colspan='4' align='center'>";
      echo "<input type='submit' name='saveConfig' value='" . self::e(__('Save')) . "' class='submit'>";
      echo "</td></tr>";

      // ── Logo section ──────────────────────────────────────────────────────
      echo "<tr><th colspan='4'>" . __('Company logo', 'qrcodelabel') . "</th></tr>";

      $logoPath = GLPI_PLUGIN_DOC_DIR . '/qrcodelabel/logo.png';
      if (file_exists($logoPath)) {
         echo "<tr class='tab_bg_1'><td colspan='4' align='center'>";
         echo "<img src='" . self::e(Label::getWebPath() . '/front/document.send.php?file=qrcodelabel/logo.png&v=' . (int)@filemtime($logoPath))
            . "' width='300' alt=''/>";
         echo "</td></tr>";

         echo "<tr class='tab_bg_1'><td colspan='4' align='center'>";
         echo "<input type='submit' name='dropLogo' value='"
            . self::e(__('Delete the logo', 'qrcodelabel')) . "' class='submit'>";
         echo "</td></tr>";
      }

      echo "<tr class='tab_bg_1'>";
      echo "<td colspan='2' align='center'><input type='file' name='logo' accept='image/png,image/jpeg,image/gif' /></td>";
      echo "<td colspan='2'><input type='submit' name='uploadLogo' value='"
         . self::e(__('Upload logo', 'qrcodelabel')) . "' class='submit'></td>";
      echo "</tr>";

      echo "</table></div>";

      // ── Label content: configurable field list ─────────────────────────────
      $this->showLabelFieldsSection();

      // ── Print Profiles section (inside the same form) ──────────────────────
      $this->showPrintProfilesSection();

      Html::closeForm();
   }

   /**
    * Show the configurable list of fields printed in the right-hand column.
    *
    * Rows are submitted in display order (lf_*_0 … lf_*_n). Reorder / delete /
    * add actions first persist the current edits, then apply the move, so an
    * unsaved font-size change is never lost.
    */
   private function showLabelFieldsSection(): void {
      $config  = self::getConfig();
      $labels  = Fields::labels();

      // Which content set is the editor working on: the global configuration
      // ('global') or one print profile ('p<id>'). Carried through GET so it
      // survives the reloads triggered by reorder / add / delete actions.
      $target   = (string)($_GET['lf_target'] ?? 'global');
      $profileId = 0;
      if (preg_match('/^p(\d+)$/', $target, $m)) {
         $profileId = (int)$m[1];
      }

      $targetName   = '';
      $targetInherit = false;
      $targetMode    = (string)($config['color_mode'] ?? 'bw');
      $fields = null;
      if ($profileId > 0) {
         global $DB;
         $row = null;
         if ($DB instanceof \DBmysql && $DB->tableExists('glpi_plugin_qrcodelabel_printprofiles')) {
            foreach ($DB->request(['FROM' => 'glpi_plugin_qrcodelabel_printprofiles', 'WHERE' => ['id' => $profileId], 'LIMIT' => 1]) as $row) {
               break;
            }
         }
         if ($row !== null) {
            $targetName    = (string)$row['name'];
            $targetInherit = trim((string)($row['label_fields'] ?? '')) === '';
            $targetMode    = (string)($row['color_mode'] ?? $targetMode);
            $fields = Fields::parse(
               $targetInherit ? ($config['label_fields'] ?? null) : $row['label_fields'],
               $config
            );
         }
      }
      if ($profileId <= 0 || $targetName === '') {
         $profileId  = 0;
         $target     = 'global';
         $targetName = __('Global content', 'qrcodelabel');
         $fields     = Fields::parse($config['label_fields'] ?? null, $config);
      }

      $styleLabels = [
         ''   => __('Normal', 'qrcodelabel'),
         'B'  => __('Bold', 'qrcodelabel'),
         'I'  => __('Italic', 'qrcodelabel'),
         'BI' => __('Bold italic', 'qrcodelabel'),
      ];
      $colorLabels = [
         'main' => __('Text', 'qrcodelabel'),
         'sub'  => __('Secondary', 'qrcodelabel'),
         'sn'   => __('Serial', 'qrcodelabel'),
         'loc'  => __('Location', 'qrcodelabel'),
         'inv'  => __('Footnote', 'qrcodelabel'),
      ];
      $alignLabels = [
         'L' => __('Left'),
         'C' => __('Center'),
         'R' => __('Right'),
      ];

      echo "<input type='hidden' name='field_action' id='qrcl_field_action' value=''>";
      echo "<input type='hidden' name='field_idx' id='qrcl_field_idx' value=''>";
      echo "<input type='hidden' name='lf_count' value='" . count($fields) . "'>";
      echo "<input type='hidden' name='lf_target' value='" . self::e($target) . "'>";

      echo "<div class='center'>";
      echo "<table class='tab_cadre_fixe' id='qrcl_fields_table'>";

      // ── Editor target: global content or a per-profile copy ──────────────
      $targets = ['global' => __('Global content', 'qrcodelabel')];
      global $DB;
      if ($DB instanceof \DBmysql && $DB->tableExists('glpi_plugin_qrcodelabel_printprofiles')) {
         foreach ($DB->request(['FROM' => 'glpi_plugin_qrcodelabel_printprofiles', 'ORDER' => 'name ASC']) as $row) {
            $targets['p' . (int)$row['id']] = (string)$row['name'];
         }
      }

      echo "<tr><th colspan='8'>"
         . __('Label content (right-hand column)', 'qrcodelabel')
         . "</th></tr>";
      echo "<tr class='tab_bg_1'><td colspan='8'>";
      echo "<strong>" . self::e(__('Edit target', 'qrcodelabel')) . "</strong>&nbsp;&nbsp;";
      Dropdown::showFromArray('lf_target_picker', $targets, [
         'value'     => $target,
         'width'     => '220',
         'on_change' => "window.location.search = '?lf_target=' + encodeURIComponent(this.value)",
      ]);
      if ($profileId > 0) {
         echo "&nbsp;<em class='small'>"
            . self::e($targetInherit
               ? __('This print profile inherits the global content. Edit and save below to give it its own copy.', 'qrcodelabel')
               : sprintf(__('Custom content of "%s". Use "Restore default content" to inherit the global content again.', 'qrcodelabel'), $targetName))
            . "</em>";
      }
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'><td colspan='8' class='text-muted small'>"
         . self::e(__('Lines are printed top to bottom in this order. The generate dialog switches the label content together with the print profile; a profile with its own copy uses it, the others use the global content.', 'qrcodelabel'))
         . "</td></tr>";

      echo "<tr class='tab_bg_2'>";
      echo "<th>" . __('Field', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Prefix', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Font size (pt)', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Style', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Text color', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Alignment', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Order', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Actions') . "</th>";
      echo "</tr>";

      foreach ($fields as $i => $field) {
         $idx = (int)$i;
         echo "<tr class='tab_bg_1'>";

         echo "<td>";
         Dropdown::showFromArray("lf_key_{$idx}", $labels, [
            'value' => $field['key'],
            'width' => '160',
         ]);
         echo "</td>";

         echo "<td><input type='text' name='lf_prefix_{$idx}' value='" . self::e($field['prefix'])
            . "' size='10' maxlength='40' placeholder='" . self::e(__('e.g. S/N: ', 'qrcodelabel')) . "'></td>";

         echo "<td><input type='number' name='lf_pt_{$idx}' value='" . self::e((string)(float)$field['pt'])
            . "' min='3' max='30' step='0.5' style='width:5em'></td>";

         echo "<td>";
         Dropdown::showFromArray("lf_style_{$idx}", $styleLabels, [
            'value' => $field['style'],
            'width' => '130',
         ]);
         echo "</td>";

         echo "<td>";
         Dropdown::showFromArray("lf_color_{$idx}", $colorLabels, [
            'value' => $field['color'],
            'width' => '120',
         ]);
         echo "</td>";

         echo "<td>";
         Dropdown::showFromArray("lf_align_{$idx}", $alignLabels, [
            'value' => $field['align'],
            'width' => '90',
         ]);
         echo "</td>";

         echo "<td style='white-space:nowrap'>";
         echo "<button type='submit' class='submit' title='" . self::e(__('Move up', 'qrcodelabel'))
            . "' onclick=\"qrclFieldAction('up',{$idx});\">&uarr;</button> ";
         echo "<button type='submit' class='submit' title='" . self::e(__('Move down', 'qrcodelabel'))
            . "' onclick=\"qrclFieldAction('down',{$idx});\">&darr;</button>";
         echo "</td>";

         echo "<td><button type='submit' class='submit' title='" . self::e(__('Delete')) . "'"
            . " onclick=\"qrclFieldAction('del',{$idx});\">&times;</button></td>";

         echo "</tr>";
      }

      // ── Add a field ───────────────────────────────────────────────────────
      echo "<tr class='tab_bg_1'>";
      echo "<td>";
      Dropdown::showFromArray('lf_key_new', $labels, ['value' => '', 'width' => '160']);
      echo "</td>";
      echo "<td colspan='5' class='text-muted small'>"
         . self::e(__('New fields are appended at the end. Font sizes refer to the 36 mm tape and are scaled proportionally for the other sizes.', 'qrcodelabel'))
         . "</td>";
      echo "<td colspan='2'>";
      echo "<button type='submit' class='submit' onclick=\"document.getElementById('qrcl_field_action').value='add';"
         . "document.getElementById('qrcl_field_idx').value='';\">" . self::e(__('Add field', 'qrcodelabel')) . "</button>";
      echo "</td>";
      echo "</tr>";

      // ── Preview ───────────────────────────────────────────────────────────
      echo "<tr><th colspan='8'>" . __('Preview (36 mm)', 'qrcodelabel') . "</th></tr>";
      echo "<tr class='tab_bg_1'><td colspan='8' align='center'>";
      echo "<div id='qrcl_preview' style='display:inline-block;width:70mm;height:36mm;box-sizing:border-box;"
         . "border:1px solid #ccc;padding:2mm 3mm;text-align:left;background:#fff;color:#000;overflow:hidden'></div>";
      echo "<div class='text-muted small mt-1'>"
         . self::e(__('Approximate rendering. The generator also tightens spacing and shrinks the text when a label is too small.', 'qrcodelabel'))
         . "</div>";
      echo "</td></tr>";

      // ── Save + Reset ──────────────────────────────────────────────────────
      if ($profileId > 0) {
         $resetMsg   = __('Revert this print profile to the inherited global content?', 'qrcodelabel');
         $resetLabel = __('Revert to inherited content', 'qrcodelabel');
      } else {
         $resetMsg   = __('Restore the default label content?');
         $resetLabel = __('Restore default content', 'qrcodelabel');
      }
      echo "<tr class='tab_bg_1'><td colspan='8' align='center'>";
      echo "<button type='submit' class='submit' onclick=\"document.getElementById('qrcl_field_action').value='save';"
         . "document.getElementById('qrcl_field_idx').value='';\">"
         . self::e(__('Save label content', 'qrcodelabel')) . "</button>&nbsp;&nbsp;";
      echo "<button type='submit' class='submit' onclick=\"if(!confirm("
         . self::e(json_encode($resetMsg, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP))
         . "))return false;document.getElementById('qrcl_field_action').value='reset';\">"
         . self::e($resetLabel) . "</button>";
      echo "</td></tr>";

      echo "</table></div>";

      // Preview palette: exactly the colors the current edit target's color
      // mode will print, so the preview is not misleading.
      $theme = Label::theme($targetMode);
      $hex   = static function (array $rgb): string {
         return sprintf('#%02x%02x%02x', (int)$rgb[0], (int)$rgb[1], (int)$rgb[2]);
      };
      $previewTheme = [
         'colors' => [
            'main' => $hex($theme['main']),
            'sub'  => $hex($theme['sub']),
            'sn'   => $hex($theme['sn']),
            'loc'  => $hex($theme['loc']),
            'inv'  => $hex($theme['inv']),
         ],
         'bg'     => $hex($theme['bg']),
         'border' => $hex($theme['border']),
      ];

      echo "<script type='text/javascript'>\n";
      echo "var qrclSamples = " . json_encode(Fields::samples(), JSON_UNESCAPED_UNICODE) . ";\n";
      echo "var qrclPreviewTheme = " . json_encode($previewTheme) . ";\n";
      echo <<<'JS'
function qrclFieldAction(action, idx) {
   document.getElementById('qrcl_field_action').value = action;
   document.getElementById('qrcl_field_idx').value = idx;
}
function qrclRenderPreview() {
   var box = document.getElementById('qrcl_preview');
   if (!box) { return; }
   var countInput = document.querySelector('[name="lf_count"]');
   var count = countInput ? parseInt(countInput.value, 10) : 0;
   var colors = qrclPreviewTheme.colors;
   var aligns = {L: 'left', C: 'center', R: 'right'};
   box.style.background = qrclPreviewTheme.bg;
   box.style.borderColor = qrclPreviewTheme.border;
   function val(name) {
      var el = document.querySelector('[name="' + name + '"]');
      return el ? el.value : '';
   }
   var html = '';
   for (var i = 0; i < count; i++) {
      var key = val('lf_key_' + i);
      if (!key) { continue; }
      var text = val('lf_prefix_' + i) + (qrclSamples[key] || '');
      if (!text) { continue; }
      var pt = parseFloat(val('lf_pt_' + i)) || 6;
      var style = val('lf_style_' + i);
      var align = val('lf_align_' + i);
      html += '<div style="font-size:' + (pt * 1.333).toFixed(1) + 'px;line-height:1.15;'
         + 'font-weight:' + (style.indexOf('B') >= 0 ? 'bold' : 'normal') + ';'
         + 'font-style:' + (style.indexOf('I') >= 0 ? 'italic' : 'normal') + ';'
         + 'color:' + (colors[val('lf_color_' + i)] || '#000') + ';'
         + 'text-align:' + (aligns[align] || 'left') + ';'
         + 'overflow:hidden;white-space:nowrap">'
         + text.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</div>';
   }
   box.innerHTML = html || '<span style="color:#999">' + '(empty)' + '</span>';
}
document.addEventListener('DOMContentLoaded', function () {
   qrclRenderPreview();
   var table = document.getElementById('qrcl_fields_table');
   if (table) {
      table.addEventListener('input', qrclRenderPreview);
      table.addEventListener('change', qrclRenderPreview);
   }
});
JS;
      echo "\n</script>";
   }

   /**
    * Show the print profiles management section.
    */
   private function showPrintProfilesSection(): void {

      $colorModeLabels = [
         'bw'           => __('Black & White', 'qrcodelabel'),
         'mono'         => __('Monochrome', 'qrcodelabel'),
         'color'        => __('Color', 'qrcodelabel'),
         'inverse'      => __('Inverse (white on black)', 'qrcodelabel'),
         'inverse_mono' => __('Inverse Mono', 'qrcodelabel'),
      ];
      $orientationLabels = [
         'Portrait'  => __('Portrait', 'qrcodelabel'),
         'Landscape' => __('Landscape', 'qrcodelabel'),
      ];
      $tapeSizeOptions = [
         '24mm' => '24 mm (Brother)',
         '25mm' => '25 mm',
         '36mm' => '36 mm (Brother)',
         '50mm' => '50 mm',
      ];
      $pageSizeOptions = ['A4' => 'A4', 'A3' => 'A3', 'LETTER' => 'Letter', 'LEGAL' => 'Legal'];

      // Hidden fields for profile actions (set by JavaScript before submit)
      echo "<input type='hidden' name='profile_action' id='qrcl_profile_action' value=''>";
      echo "<input type='hidden' name='profile_id' id='qrcl_profile_id' value=''>";

      echo "<div class='center'>";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr><th colspan='8'>"
         . __('Print profiles', 'qrcodelabel')
         . "</th></tr>";

      // Table header
      echo "<tr class='tab_bg_2'>";
      echo "<th>" . __('Name') . "</th>";
      echo "<th>" . __('Tape size', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Color mode', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Show inventory date', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Page size', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Orientation', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Default', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Actions') . "</th>";
      echo "</tr>";

      // List existing profiles
      global $DB;
      $iterator = $DB->request([
         'FROM'  => 'glpi_plugin_qrcodelabel_printprofiles',
         'ORDER' => 'name ASC',
      ]);

      foreach ($iterator as $row) {
         $pid = (int)$row['id'];
         echo "<tr class='tab_bg_1'>";

         // Name (editable) — use unique names per row
         echo "<td><input type='text' name='pp_name_{$pid}' value='"
            . self::e(Label::rawText($row['name'])) . "' size='15' maxlength='100'>";
         $ownLines = count(Fields::parse($row['label_fields'] ?? null, []));
         echo "<br><span class='small text-muted'>"
            . self::e(trim((string)($row['label_fields'] ?? '')) === ''
               ? __('Inherits global content', 'qrcodelabel')
               : sprintf(__('Custom content (%d lines)', 'qrcodelabel'), $ownLines))
            . "</span></td>";

         // Tape size
         echo "<td>";
         Dropdown::showFromArray("pp_tape_{$pid}", $tapeSizeOptions,
            ['value' => $row['tape_size'], 'width' => '170']);
         echo "</td>";

         // Color mode
         echo "<td>";
         Dropdown::showFromArray("pp_color_{$pid}", $colorModeLabels,
            ['value' => $row['color_mode'], 'width' => '150']);
         echo "</td>";

         // Show date
         echo "<td>";
         Dropdown::showYesNo("pp_date_{$pid}", $row['show_date'], -1, ['width' => '80']);
         echo "</td>";

         // Page size
         echo "<td>";
         Dropdown::showFromArray("pp_page_{$pid}", $pageSizeOptions,
            ['value' => $row['page_size'], 'width' => '100']);
         echo "</td>";

         // Orientation
         echo "<td>";
         Dropdown::showFromArray("pp_orient_{$pid}", $orientationLabels,
            ['value' => $row['orientation'], 'width' => '100']);
         echo "</td>";

         // Is default
         echo "<td>";
         Dropdown::showYesNo("pp_default_{$pid}", $row['is_default'], -1, ['width' => '80']);
         echo "</td>";

         // Actions: Save + Delete buttons
         echo "<td>";
         echo "<button type='submit' class='submit' onclick=\"document.getElementById('qrcl_profile_action').value='update';"
            . "document.getElementById('qrcl_profile_id').value='{$pid}';\">"
            . self::e(__('Save')) . "</button> ";
         echo "<button type='submit' class='submit' onclick=\"if(!confirm("
            . self::e(json_encode(__('Are you sure?'), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) . "))return false;"
            . "document.getElementById('qrcl_profile_action').value='delete';"
            . "document.getElementById('qrcl_profile_id').value='{$pid}';\">"
            . self::e(__('Delete')) . "</button>";
         echo "</td></tr>";
      }

      // Add new profile row
      echo "<tr><th colspan='8'>"
         . __('Add a print profile', 'qrcodelabel')
         . "</th></tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td><input type='text' name='pp_name_new' value='' size='15' maxlength='100' "
         . "placeholder='" . self::e(__('Name')) . "'></td>";

      echo "<td>";
      Dropdown::showFromArray('pp_tape_new', $tapeSizeOptions,
         ['value' => '36mm', 'width' => '170']);
      echo "</td>";

      echo "<td>";
      Dropdown::showFromArray('pp_color_new', $colorModeLabels,
         ['value' => 'bw', 'width' => '150']);
      echo "</td>";

      echo "<td>";
      Dropdown::showYesNo('pp_date_new', 1, -1, ['width' => '80']);
      echo "</td>";

      echo "<td>";
      Dropdown::showFromArray('pp_page_new', $pageSizeOptions,
         ['value' => 'A4', 'width' => '100']);
      echo "</td>";

      echo "<td>";
      Dropdown::showFromArray('pp_orient_new', $orientationLabels,
         ['value' => 'Portrait', 'width' => '100']);
      echo "</td>";

      echo "<td>";
      Dropdown::showYesNo('pp_default_new', 0, -1, ['width' => '80']);
      echo "</td>";

      echo "<td><button type='submit' class='submit' onclick=\"document.getElementById('qrcl_profile_action').value='add';"
         . "document.getElementById('qrcl_profile_id').value='new';\">"
         . self::e(__('Add')) . "</button></td>";

      echo "</tr></table></div>";
   }
}
