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
use Entity;
use User;

/**
 * Configurable text fields printed in the right-hand column of a label.
 *
 * A label field is a small array:
 *   [
 *     'key'    => 'serial',   // one of catalogue()
 *     'prefix' => 'S/N: ',    // literal text drawn before the value
 *     'pt'     => 6.5,        // font size at the 36 mm reference preset
 *     'style'  => 'B',        // '', 'B', 'I', 'BI'
 *     'color'  => 'sn',       // main | sub | sn | loc | inv (see Label::$colorModes)
 *     'align'  => 'L',        // L | C | R
 *   ]
 *
 * Point sizes are authored for the 36 mm tape and follow other tape sizes
 * through the same ratio the built-in presets use (see Label::layoutText()).
 *
 * The ordered list is stored as JSON in glpi_plugin_qrcodelabel_configs.label_fields.
 */
class Fields {

   /** Hard limit on configured lines. */
   public const MAX_FIELDS = 12;

   /** Reference font size the presets were designed for (36 mm tape). */
   public const BASE_PT = 9.0;

   public const COLORS = ['main', 'sub', 'sn', 'loc', 'inv'];
   public const STYLES = ['', 'B', 'I', 'BI'];
   public const ALIGNS = ['L', 'C', 'R'];

   /** Font size bounds accepted from the configuration form. */
   private const MIN_PT = 3.0;
   private const MAX_PT = 30.0;

   /**
    * All selectable fields: key => [label, sample value used by the preview].
    *
    * A key that does not exist on a given asset type simply renders nothing,
    * so mixed fleets can share one configuration.
    *
    * @return array<string, array{label: string, sample: string}>
    */
   public static function catalogue(): array {
      $base = [
         'name'        => ['label' => __('Asset name', 'qrcodelabel'),          'sample' => 'SYL-2022-001'],
         'itemtype'    => ['label' => __('Asset type', 'qrcodelabel'),          'sample' => __('Computer')],
         'type'        => ['label' => __('Asset sub-type', 'qrcodelabel'),      'sample' => __('Laptop')],
         'serial'      => ['label' => __('Serial number', 'qrcodelabel'),       'sample' => 'PF3P9EPB'],
         'otherserial' => ['label' => __('Inventory number', 'qrcodelabel'),    'sample' => 'DZ015'],
         'location'    => ['label' => __('Location', 'qrcodelabel'),            'sample' => __('Changzhou')],
         'manufacturer' => ['label' => __('Manufacturer', 'qrcodelabel'),       'sample' => 'Lenovo'],
         'model'       => ['label' => __('Model', 'qrcodelabel'),               'sample' => 'ThinkPad T14'],
         'state'       => ['label' => __('Status', 'qrcodelabel'),              'sample' => __('In use')],
         'user'        => ['label' => __('User', 'qrcodelabel'),                'sample' => __('Zhang Wei')],
         'group'       => ['label' => __('Group', 'qrcodelabel'),               'sample' => 'IT'],
         'group_tech'  => ['label' => __('Technician group', 'qrcodelabel'),   'sample' => 'IT'],
         'supplier'    => ['label' => __('Supplier', 'qrcodelabel'),            'sample' => 'Vendor Co.'],
         'entity'      => ['label' => __('Entity', 'qrcodelabel'),              'sample' => __('Head office')],
         'contact'     => ['label' => __('Contact', 'qrcodelabel'),             'sample' => __('Li Na')],
         'contact_num' => ['label' => __('Contact phone', 'qrcodelabel'),       'sample' => '13800000000'],
         'uuid'        => ['label' => __('UUID', 'qrcodelabel'),                'sample' => '4C4C4544-0053-3410'],
         'comment'     => ['label' => __('Comment', 'qrcodelabel'),             'sample' => __('Note', 'qrcodelabel')],
         'date_creation' => ['label' => __('Creation date', 'qrcodelabel'),     'sample' => '07/07/2023'],
         'last_inventory_update' => ['label' => __('Last inventory date', 'qrcodelabel'), 'sample' => '01/02/2024'],
         'warranty_date' => ['label' => __('Warranty end', 'qrcodelabel'),      'sample' => '31/12/2026'],
         'purchase_date' => ['label' => __('Purchase date', 'qrcodelabel'),     'sample' => '01/06/2023'],
         'id'          => ['label' => __('ID', 'qrcodelabel'),                  'sample' => '1234'],
         'owner_text'  => ['label' => __('Owner text (global setting)', 'qrcodelabel'), 'sample' => 'Property of ACME'],
         'url'         => ['label' => __('Asset URL', 'qrcodelabel'),           'sample' => 'http://glpi/front/computer.form.php?id=1'],
         'generated'   => ['label' => __('Label generation time', 'qrcodelabel'), 'sample' => '10/11/2026 10:36'],
      ];

      return array_merge($base, self::pluginFieldsCatalogue());
   }

   /** Request-scoped cache for pluginFieldsCatalogue(). */
   private static $pluginFieldsCat = null;

   /**
    * Custom fields contributed by the "Additional fields" (fields) plugin.
    *
    * Every active container applicable to any asset type contributes its
    * fields as keys "af_c<container>_f<field>". A key that does not match a
    * given asset type simply renders nothing, so one configuration covers a
    * mixed fleet. When the plugin tables are absent (not installed, or data
    * left over after an uninstall) an empty list is returned and the picker
    * only shows the built-in fields.
    *
    * @return array<string, array{label: string, sample: string}>
    */
   private static function pluginFieldsCatalogue(): array {
      if (is_array(self::$pluginFieldsCat)) {
         return self::$pluginFieldsCat;
      }

      self::$pluginFieldsCat = [];
      global $DB;
      if (!($DB instanceof \DBmysql)
         || !$DB->tableExists('glpi_plugin_fields_containers')
         || !$DB->tableExists('glpi_plugin_fields_fields')) {
         return self::$pluginFieldsCat;
      }

      try {
         $containers = $DB->request([
            'FROM'  => 'glpi_plugin_fields_containers',
            'WHERE' => ['is_active' => 1],
         ]);

         foreach ($containers as $container) {
            $applicable = json_decode((string)($container['itemtypes'] ?? ''), true);
            if (!is_array($applicable)) {
               continue;
            }
            $cid = (int)($container['id'] ?? 0);
            if ($cid <= 0) {
               continue;
            }

            $fields = $DB->request([
               'FROM'  => 'glpi_plugin_fields_fields',
               'WHERE' => [
                  'plugin_fields_containers_id' => $cid,
                  'is_active'                   => 1,
               ],
               'ORDER' => ['ranking'],
            ]);

            foreach ($fields as $f) {
               $fid = (int)($f['id'] ?? 0);
               $label = trim((string)($f['label'] ?? ''));
               if ($fid <= 0 || $label === '') {
                  continue;
               }
               self::$pluginFieldsCat['af_c' . $cid . '_f' . $fid] = [
                  'label'  => $label,
                  'sample' => __('Custom field', 'qrcodelabel'),
               ];
            }
         }
      } catch (\Throwable $e) {
         // Broken plugin data must never take the whole config page down.
         self::$pluginFieldsCat = [];
      }

      return self::$pluginFieldsCat;
   }

   /** @return array<string, string> key => translated label */
   public static function labels(): array {
      $labels = [];
      foreach (self::catalogue() as $key => $meta) {
         $labels[$key] = $meta['label'];
      }
      return $labels;
   }

   /** @return array<string, string> key => sample value (label preview) */
   public static function samples(): array {
      $samples = [];
      foreach (self::catalogue() as $key => $meta) {
         $samples[$key] = $meta['sample'];
      }
      return $samples;
   }

   /**
    * Line list reproducing the historical hard-coded layout.
    */
   public static function defaults(bool $showDate = true, bool $showLocation = false, string $ownerText = ''): array {
      $list = [
         ['key' => 'name',     'prefix' => '',      'pt' => 9.0, 'style' => 'B', 'color' => 'main', 'align' => 'L'],
         ['key' => 'itemtype', 'prefix' => '',      'pt' => 5.5, 'style' => '',  'color' => 'sub',  'align' => 'L'],
         ['key' => 'serial',   'prefix' => 'S/N: ', 'pt' => 6.5, 'style' => 'B', 'color' => 'sn',   'align' => 'L'],
      ];
      if ($showDate) {
         $list[] = ['key' => 'date_creation', 'prefix' => 'Inv : ', 'pt' => 6.0, 'style' => '', 'color' => 'sub', 'align' => 'L'];
      }
      if ($showLocation) {
         $list[] = ['key' => 'location', 'prefix' => '', 'pt' => 6.0, 'style' => 'I', 'color' => 'loc', 'align' => 'L'];
      }
      if (trim($ownerText) !== '') {
         $list[] = ['key' => 'owner_text', 'prefix' => '', 'pt' => 5.5, 'style' => '', 'color' => 'inv', 'align' => 'L'];
      }
      $list[] = ['key' => 'otherserial', 'prefix' => 'Inv : ', 'pt' => 5.5, 'style' => '', 'color' => 'inv', 'align' => 'R'];

      return $list;
   }

   /**
    * Decode + validate a stored configuration.
    *
    * Empty or invalid storage falls back to defaults(), so an installation
    * upgraded from a version without configurable fields keeps its layout.
    *
    * @param mixed $raw    JSON string (or already decoded array).
    * @param array $config Global configuration (drives the defaults).
    */
   public static function parse($raw, array $config = []): array {
      $list = null;
      if (is_array($raw)) {
         $list = $raw;
      } else if (is_string($raw) && trim($raw) !== '') {
         $decoded = json_decode($raw, true);
         if (is_array($decoded)) {
            $list = $decoded;
         }
      }

      $fallback = static function () use ($config): array {
         return self::defaults(
            (bool)($config['show_date'] ?? 1),
            (bool)($config['show_location'] ?? 0),
            (string)($config['owner_text'] ?? '')
         );
      };

      if (empty($list)) {
         return $fallback();
      }

      $catalogue = self::catalogue();
      $clean     = [];
      foreach (array_slice($list, 0, self::MAX_FIELDS) as $row) {
         if (!is_array($row)) {
            continue;
         }
         $key = (string)($row['key'] ?? '');
         if (!isset($catalogue[$key])) {
            continue;
         }
         $clean[] = [
            'key'    => $key,
            'prefix' => mb_substr((string)($row['prefix'] ?? ''), 0, 40),
            'pt'     => self::clampPt($row['pt'] ?? 6),
            'style'  => in_array(($row['style'] ?? ''), self::STYLES, true) ? (string)$row['style'] : '',
            'color'  => in_array(($row['color'] ?? ''), self::COLORS, true) ? (string)$row['color'] : 'main',
            'align'  => in_array(($row['align'] ?? ''), self::ALIGNS, true) ? (string)$row['align'] : 'L',
         ];
      }

      return !empty($clean) ? $clean : $fallback();
   }

   /** Serialize a validated list for storage. */
   public static function encode(array $list): string {
      return (string)json_encode($list, JSON_UNESCAPED_UNICODE);
   }

   /**
    * Read the posted rows of the configuration form, in display order.
    *
    * @param array  $post   $_POST
    * @param string $prefix Field-name prefix
    */
   public static function fromPost(array $post, string $prefix = 'lf_'): array {
      $count = (int)($post[$prefix . 'count'] ?? 0);
      $count = max(0, min(self::MAX_FIELDS, $count));

      $list = [];
      for ($i = 0; $i < $count; $i++) {
         $key = (string)($post[$prefix . 'key_' . $i] ?? '');
         if ($key === '') {
            continue;
         }
         $list[] = [
            'key'    => $key,
            'prefix' => mb_substr((string)($post[$prefix . 'prefix_' . $i] ?? ''), 0, 40),
            'pt'     => self::clampPt($post[$prefix . 'pt_' . $i] ?? 6),
            'style'  => (string)($post[$prefix . 'style_' . $i] ?? ''),
            'color'  => (string)($post[$prefix . 'color_' . $i] ?? 'main'),
            'align'  => (string)($post[$prefix . 'align_' . $i] ?? 'L'),
         ];
      }

      return self::parse($list, []);
   }

   /** Clamp a requested point size into [MIN_PT, MAX_PT], rounded to 0.5. */
   public static function clampPt($value): float {
      $pt = is_numeric($value) ? (float)$value : 6.0;
      $pt = max(self::MIN_PT, min(self::MAX_PT, $pt));
      return round($pt * 2) / 2;
   }

   // ── Asset value extraction ─────────────────────────────────────────────────

   /**
    * Collect every printable value of an asset, keyed like catalogue().
    *
    * Fields missing on the item type are simply absent; the layout skips them.
    */
   public static function collect(CommonDBTM $item): array {
      $values = [];
      $fields = $item->fields;
      $id     = (int)$item->getID();

      $itemtype = $item->getType();

      $values['id']       = (string)$id;
      $values['name']     = Label::rawText($fields['name'] ?? '');
      $values['itemtype'] = (string)$itemtype::getTypeName(1);

      foreach (['serial', 'otherserial', 'contact', 'contact_num', 'uuid'] as $key) {
         if ($item->isField($key)) {
            $values[$key] = Label::rawText($fields[$key] ?? '');
         }
      }

      if ($item->isField('comment')) {
         $comment = trim((string)preg_replace(
            '/\s+/u',
            ' ',
            strip_tags((string)($fields['comment'] ?? ''))
         ));
         $values['comment'] = Label::rawText($comment);
      }

      // Dropdown-backed fields.
      $values['location']     = self::dropdownName('glpi_locations', $fields['locations_id'] ?? 0);
      $values['manufacturer'] = self::dropdownName('glpi_manufacturers', $fields['manufacturers_id'] ?? 0);
      $values['state']        = self::dropdownName('glpi_states', $fields['states_id'] ?? 0);
      $values['supplier']     = self::dropdownName('glpi_suppliers', $fields['suppliers_id'] ?? 0);
      $values['user']         = self::userName($fields['users_id'] ?? 0);
      $values['entity']       = self::entityName($fields['entities_id'] ?? 0);

      // Groups: a plain groups_id column up to GLPI 11, a glpi_groups_items
      // relation table from GLPI 12 on.
      $values['group']      = self::groupName($item, 1);
      $values['group_tech'] = self::groupName($item, 2);

      // Timestamp printed on the label: the moment this batch is generated,
      // identical for every asset of the run (DD/MM/YYYY HH:MM).
      $values['generated'] = date('d/m/Y H:i');

      // Model / sub-type columns are named <something>models_id and
      // <something>types_id (computermodels_id, printertypes_id, ...), which
      // maps directly onto glpi_<something>models / glpi_<something>types.
      $aliases = ['models' => 'model', 'types' => 'type'];
      foreach ($fields as $field => $value) {
         if (!preg_match('/^(.+?)(models|types)_id$/', (string)$field, $m)) {
            continue;
         }
         $group = $aliases[$m[2]];   // catalogue key: model | type
         if (isset($values[$group])) {
            continue;
         }
         $table = 'glpi_' . $m[1] . $m[2];   // glpi_computermodels, glpi_printertypes
         $name  = self::dropdownName($table, $value);
         if ($name !== '') {
            $values[$group] = $name;
         }
      }

      // Dates, normalised to DD/MM/YYYY.
      foreach (['date_creation', 'date_mod', 'last_inventory_update', 'warranty_date', 'purchase_date'] as $key) {
         if ($item->isField($key)) {
            $values[$key] = self::formatDate((string)($fields[$key] ?? ''));
         }
      }

      // Custom fields contributed by the Additional fields (fields) plugin.
      $values += self::collectPluginFields($item);

      return $values;
   }

   /** Request-scoped cache: itemtype => key => [container, field definition]. */
   private static $pfDataMap = null;

   /**
    * Values of the Additional fields (fields) plugin for this item.
    *
    * @return array<string, string> af_c<container>_f<field> => printable value
    */
   private static function collectPluginFields(CommonDBTM $item): array {
      global $DB;
      if (!($DB instanceof \DBmysql)
         || !$DB->tableExists('glpi_plugin_fields_containers')
         || !$DB->tableExists('glpi_plugin_fields_fields')) {
         return [];
      }

      $itemtype = $item->getType();
      if (self::$pfDataMap === null || !array_key_exists($itemtype, self::$pfDataMap)) {
         self::$pfDataMap = is_array(self::$pfDataMap) ? self::$pfDataMap : [];
         self::$pfDataMap[$itemtype] = self::pluginFieldsDataMap($itemtype);
      }

      $id     = (int)$item->getID();
      $values = [];
      foreach (self::$pfDataMap[$itemtype] as $key => $def) {
         $table  = (string)$def['table'];
         $column = (string)$def['column'];

         $where = ['items_id' => $id];
         if ($def['has_itemtype_col']) {
            $where['itemtype'] = $itemtype;
         }
         $rows = $DB->request(['FROM' => $table, 'WHERE' => $where, 'LIMIT' => 1]);
         foreach ($rows as $row) {
            $val = $row[$column] ?? null;
            $values[$key] = self::renderPluginFieldValue($val, (string)$def['type'], $def);
            break;
         }
      }

      return $values;
   }

   /**
    * Locate, for one itemtype, the data table + column of every applicable
    * Additional fields container.
    *
    * @return array<string, array{table: string, column: string, type: string, has_itemtype_col: bool, container: array, field: array}>
    */
   private static function pluginFieldsDataMap(string $itemtype): array {
      global $DB;

      $map = [];

      try {
         $containers = $DB->request([
            'FROM'  => 'glpi_plugin_fields_containers',
            'WHERE' => ['is_active' => 1],
         ]);

         foreach ($containers as $container) {
            $applicable = json_decode((string)($container['itemtypes'] ?? ''), true);
            if (!is_array($applicable) || !in_array($itemtype, $applicable, true)) {
               continue;
            }
            $cid  = (int)($container['id'] ?? 0);
            $cname = (string)($container['name'] ?? '');
            if ($cid <= 0 || $cname === '') {
               continue;
            }

            // Data table: the plugin names it after the GLPI class it creates
            // (glpi_plugin_fields_ + lowercase itemtype + container name +
            // GLPI pluralisation), and the convention changed across releases.
            // Scan the real table list instead of guessing.
            $table = self::findPluginFieldsDataTable($itemtype, $cname);
            if ($table === null) {
               continue;
            }

            $fields = $DB->request([
               'FROM'  => 'glpi_plugin_fields_fields',
               'WHERE' => [
                  'plugin_fields_containers_id' => $cid,
                  'is_active'                   => 1,
               ],
               'ORDER' => ['ranking'],
            ]);

            foreach ($fields as $f) {
               $column = (string)($f['name'] ?? '');
               $fid    = (int)($f['id'] ?? 0);
               $label  = trim((string)($f['label'] ?? ''));
               if ($fid <= 0 || $column === '' || !$DB->fieldExists($table, $column)) {
                  continue;
               }
               $map['af_c' . $cid . '_f' . $fid] = [
                  'table'            => $table,
                  'column'           => $column,
                  'type'             => (string)($f['type'] ?? 'text'),
                  'has_itemtype_col' => $DB->fieldExists($table, 'itemtype'),
                  'container'        => $container,
                  'field'            => $f,
               ];
            }
         }
      } catch (\Throwable $e) {
         return [];
      }

      return $map;
   }

   /**
    * Locate the data table the fields plugin created for one itemtype +
    * container pair, by scanning the real table list.
    *
    * Observed convention on GLPI 12: glpi_plugin_fields_<lowercase itemtype>
    * <container name> with GLPI's pluralisation appended (a container name
    * ending in "x" becomes "...xes"). Older releases used other separators,
    * so the scan is kept loose: tables starting with
    * glpi_plugin_fields_<itemtype> whose name contains the container name.
    */
   private static function findPluginFieldsDataTable(string $itemtype, string $cname): ?string {
      $tables = self::pluginFieldsTableNames('glpi_plugin_fields_' . strtolower($itemtype));
      foreach ($tables as $table) {
         $low = strtolower($table);
         if (strpos($low, strtolower($cname)) !== false) {
            return $table;
         }
      }

      // Fallback for information_schema restrictions: probe common patterns.
      global $DB;
      $low  = strtolower($itemtype);
      $endsX = (bool)preg_match('/(?:x|ch|sh|s|z)$/i', $cname);
      foreach ([
         'glpi_plugin_fields_' . $low . $cname . ($endsX ? 'es' : 's'),
         'glpi_plugin_fields_' . $low . $cname . 'es',
         'glpi_plugin_fields_' . $low . $cname,
         'glpi_plugin_fields_' . $low . '_' . $cname,
         'glpi_plugin_fields_' . $low . 's' . $cname,
      ] as $candidate) {
         if ($DB->tableExists($candidate)) {
            return $candidate;
         }
      }

      return null;
   }

   /** @return string[] names of tables matching "<prefix>%" (prefix escaped). */
   private static function pluginFieldsTableNames(string $prefix): array {
      global $DB;
      $out = [];
      try {
         $rows = $DB->request([
            'SELECT' => ['TABLE_NAME'],
            'FROM'   => 'information_schema.TABLES',
            'WHERE'  => [
               'TABLE_SCHEMA' => $DB->dbdefault,
               'TABLE_NAME'   => ['LIKE', str_replace('_', '\\_', $prefix) . '%'],
            ],
         ]);
         foreach ($rows as $row) {
            $out[] = (string)$row['TABLE_NAME'];
         }
      } catch (\Throwable $e) {
         // handled by the caller's fallback
      }
      return $out;
   }

   /** Convert a stored Additional fields value into printable text. */
   private static function renderPluginFieldValue($val, string $type, array $def): string {
      if ($val === null || $val === '') {
         return '';
      }

      if ($type === 'date' || $type === 'datetime') {
         return self::formatDate(substr((string)$val, 0, 19));
      }

      if ($type === 'dropdown') {
         return self::pluginDropdownName($val, $def);
      }

      if ($type === 'yesno') {
         return (string)$val === '1' ? __('Yes') : __('No');
      }

      return Label::rawText(trim((string)$val));
   }

   /**
    * Resolve the label of an Additional fields dropdown value.
    *
    * The options table naming changed across plugin releases (and long names
    * hit MySQL's 64-character limit, so they may be truncated); scan the real
    * table list for a "*<column>dropdowns" table and fall back to ''.
    */
   private static function pluginDropdownName($val, array $def): string {
      global $DB;

      $val = (int)$val;
      if ($val <= 0 || !($DB instanceof \DBmysql)) {
         return '';
      }

      $column = (string)($def['column'] ?? '');
      if ($column === '') {
         return '';
      }

      // Prefer options tables attached to the same itemtype as the data table.
      $dataTable = strtolower((string)($def['table'] ?? ''));
      $tables    = self::pluginFieldsTableNames('glpi_plugin_fields_');
      usort($tables, static function ($a, $b) use ($dataTable) {
         $pa = strpos(strtolower($a), substr($dataTable, 0, 40)) === 0 ? 0 : 1;
         $pb = strpos(strtolower($b), substr($dataTable, 0, 40)) === 0 ? 0 : 1;
         return $pa <=> $pb;
      });

      foreach ($tables as $table) {
         $low = strtolower($table);
         if (substr($low, -9) === 'dropdowns' && strpos($low, strtolower($column)) !== false) {
            return self::dropdownName($table, $val);
         }
      }

      return '';
   }

   /** Read a dropdown label, tolerating tables that do not exist. */
   private static function dropdownName(string $table, $id): string {
      global $DB;

      $id = (int)$id;
      if ($id <= 0 || !($DB instanceof \DBmysql) || !$DB->tableExists($table)) {
         return '';
      }
      $name = (string)Dropdown::getDropdownName($table, $id);
      if ($name === '&nbsp;') {
         $name = '';
      }
      return Label::rawText(trim($name));
   }

   /**
    * Group names attached to the item.
    *
    * GLPI <= 11 keeps a groups_id / groups_id_tech column on the asset table;
    * GLPI >= 12 normalises both into glpi_groups_items (type 1 = group,
    * type 2 = technician group). Multiple groups are joined with ", ".
    */
   private static function groupName(CommonDBTM $item, int $type): string {
      global $DB;

      if ($item->isField('groups_id')) {
         $column = ($type === 2 && $item->isField('groups_id_tech')) ? 'groups_id_tech' : 'groups_id';
         $ids    = [(int)($item->fields[$column] ?? 0)];
      } else {
         $ids = [];
         if ($DB instanceof \DBmysql && $DB->tableExists('glpi_groups_items')) {
            try {
               $rows = $DB->request([
                  'SELECT' => ['groups_id'],
                  'FROM'   => 'glpi_groups_items',
                  'WHERE'  => [
                     'itemtype' => $item->getType(),
                     'items_id' => (int)$item->getID(),
                     'type'     => $type,
                  ],
               ]);
               foreach ($rows as $row) {
                  $ids[] = (int)$row['groups_id'];
               }
            } catch (\Throwable $e) {
               return '';
            }
         }
      }

      $names = [];
      foreach (array_unique($ids) as $gid) {
         $name = self::dropdownName('glpi_groups', $gid);
         if ($name !== '') {
            $names[] = $name;
         }
      }
      return implode(', ', $names);
   }

   private static function userName($id): string {
      $id = (int)$id;
      if ($id <= 0) {
         return '';
      }
      $user = new User();
      if (!$user->getFromDB($id)) {
         return '';
      }
      $name = method_exists($user, 'getFriendlyName')
         ? $user->getFriendlyName()
         : (string)($user->fields['name'] ?? '');
      return Label::rawText(trim((string)$name));
   }

   private static function entityName($id): string {
      $entity = new Entity();
      if (!method_exists($entity, 'getFromDB') || !$entity->getFromDB((int)$id)) {
         return '';
      }
      $name = method_exists($entity, 'getFriendlyName')
         ? $entity->getFriendlyName()
         : (string)($entity->fields['name'] ?? '');
      return Label::rawText(trim((string)$name));
   }

   /** YYYY-MM-DD[ hh:mm:ss] → DD/MM/YYYY ('' when the date is empty). */
   public static function formatDate(string $raw): string {
      $raw = trim($raw);
      if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
         return '';
      }
      $ymd = explode('-', substr($raw, 0, 10));
      return count($ymd) === 3 ? "{$ymd[2]}/{$ymd[1]}/{$ymd[0]}" : $raw;
   }
}
