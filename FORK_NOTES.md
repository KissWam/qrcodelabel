# Fork 说明（本地化改造）

本仓库 Fork 自 [tienou/glpi-qrcodelabel](https://github.com/tienou/glpi-qrcodelabel)，在其基础上做以下本地化与兼容性改造。**上游 README.md 保持原样以尊重原作者；本文件说明本 Fork 的额外改动。**

> 原作者：Etienne Gaillard。许可证：AGPL-3.0（见 LICENSE）。

## 主要改动

### 1. GLPI 12 兼容
- 适配 GLPI 12 的 `CommonGLPI::$rightname` 类型声明变更：子类不重声明，改在 `plugin_init_qrcodelabel()` 中运行时赋值
- 兼容 GLPI 11 / 12 的双分支逻辑（`rightname`、`Firewall` 路由等）
- 支持 GLPI 12 自定义资产类别（从 `system_name` 动态读取）

### 2. 中文字体与中文标签
- 内置 `fonts/DroidSansFallbackFull.ttf`、`NotoSans-*.ttf`，解决中文资产名打印为方框的问题
- `src/Label.php::findFont()` 支持中文回退字体
- 新增 `locales/zh_CN.mo/po` 中文语言包

### 3. 使用文档本地化
- 新增 `docs/操作指南.md`：针对实际打印环境（GP-1134T，300 dpi，浏览器直打）的操作步骤

## 部署方式

将目录放置为 `<GLPI_ROOT>/plugins/qrcodelabel`，在 GLPI 中安装并启用。字体文件为打印中文所必需，请勿删除。

## 同步上游

本 Fork 不定期同步上游；如需合并新功能，建议从上游 tag 手动 cherry-pick 并重新验证 GLPI 12 兼容。
