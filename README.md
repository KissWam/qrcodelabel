# QR Code Label — GLPI 插件（本地增强 Fork）

本仓库 Fork 自 [tienou/glpi-qrcodelabel](https://github.com/tienou/glpi-qrcodelabel)（原作者 Etienne Gaillard，AGPL-3.0），在本地做了**大量二次开发**。上游最后版本为 v1.5.1、仅支持 GLPI 10/11；本 Fork 已迭代至 **v1.8.2**，重点新增 GLPI 12 支持、中文标签打印与可配置标签内容。

> 上游原始功能说明保留在 [UPSTREAM.md](UPSTREAM.md) 供追溯。本 README 只讲本 Fork 二次开发后的能力与使用。

[![GLPI Version](https://img.shields.io/badge/GLPI-10.x%20%7C%2011.x%20%7C%2012.x-blue)](https://glpi-project.org/)
[![License](https://img.shields.io/badge/License-AGPL%20v3-green)](LICENSE)

---

## 一、二次开发亮点

### 1. GLPI 12 完整支持（上游不具备）

上游仅支持到 GLPI 11.0.99。本 Fork 解决 GLPI 12 的多处破坏性变更：

| 上游在 GLPI 12 的问题 | 本地修复 |
|---|---|
| 子类重声明 `CommonGLPI::$rightname` 触发 fatal（父类改为 `public static string` 类型） | 子类不再重声明，改在 `plugin_init_qrcodelabel()` 运行时赋值，兼容 10/11/12 |
| TCPDF 12 沙箱只允许读取白名单目录，导入的中文字体定义读不到 | 加载时定义 `K_ALLOWED_PATHS`，指向插件字体目录 |
| GLPI 12 资产-群组关系从 `groups_id` 列移到 `glpi_groups_items` 表，Group 字段打印为空 | 两种存储布局均支持，多个群组合并以逗号连接，新增 **Technician group** 字段 |
| GLPI 12 移除 legacy CSRF 令牌机制 | 按版本分支注册 `csrf_compliant` |
| GLPI 11/12 自定义资产类别 | 从 `system_name` 动态读取，自动获得标签页与批量动作 |

### 2. 中文标签打印（上游不具备）

- 内置 **Droid Sans Fallback** CJK 字体（`fonts/DroidSansFallbackFull.ttf`），解决中文资产名打印为空方框的问题。
- `Label::splitRuns()` 将每行文本按 CJK / 非 CJK 切分，分别用对应字体渲染——混合文本如 `研发部工作站 A 区` 可正确显示；拉丁部分保留粗体，CJK 部分用 Droid 字重。
- PDF 侧字体经 `Com\Tecnick\Pdf\Font\Import` 导入到 `files/_plugins/qrcodelabel/fonts/*.json` 并以子集嵌入，单页 PDF 约 220 KB。
- 新增简体中文语言包（`locales/zh_CN.po/.mo`）。
- 字体查找顺序：插件内置 → Linux 系统字体路径 → Windows 字体。可用自有 TTF 替换内置字体（详见 [`fonts/README.md`](fonts/README.md)）。

### 3. 可配置标签内容（上游不具备）

- 每行可选任意资产字段，独立设置字号、样式、颜色、对齐，带实时预览与"恢复默认内容"。
- **每个打印配置档可存自己的行内容**：生成标签时跟随所选配置档；未单独配置的配置档继承全局内容。
- **Additional fields（fields 插件）自定义字段**自动进入可选列表（键 `af_c<container>_f<field>`），支持文本/数字/日期/是否/dropdown 类型；不适用于某资产类型的字段在打印时自动跳过。
- 新增 **Label generation time** 字段（`generated`）：打印批次生成时刻 `DD/MM/YYYY HH:MM`。
- 布局引擎重写为单一纵向流：空间不足时先压缩行距 → 整体缩小字号（最低 60%）→ 超长值省略号截断，不静默丢弃内容。
- 黑白模式改为调色板灰度渲染（主黑/#666/#333/#999），"单色"模式才是纯黑白；预览使用当前编辑项的实际颜色模式。

### 4. 稳定性与安全性修复

- 修 GLPI 10 下所有插件页 HTTP 500（`Class "Session" not found`）；QR 编码回退到 TCPDF 自带编码器（GLPI 10 不含 `tc-lib-barcode`）。
- 修 QR 标签页自 1.2.0 起未注册的问题；生成后跳转到保留下载按钮的页面。
- 修 24/25 mm 标签字段不全、36 mm 位置/所有者文字重叠。
- 修颜色打印与预览不一致（1.8.2）、标签内容编辑器保存丢失（1.8.1）。
- 安全加固：标签下载端点限定插件文档目录且仅图片文件、批量动作强制插件权限、配置页用插件自身权限、临时文件按用户隔离、logo 重编码为真实 PNG、配置页 HTML 转义。

---

## 二、快速开始

### 安装

将目录放至 `<GLPI_ROOT>/plugins/qrcodelabel`，在 GLPI 后台启用。字体文件为打印中文所必需，请勿删除。

```bash
cd /var/www/html/glpi/plugins
git clone https://github.com/KissWam/qrcodelabel.git qrcodelabel
# GLPI 后台 → 设置 → 插件 → 安装并启用
```

### 打印中文标签（浏览器直打示例）

针对实际打印环境（GP-1134T，300 dpi，USB 直连）的完整步骤见 [`docs/操作指南.md`](docs/操作指南.md)。通用流程：

1. 打开任一资产 → **QR Label** 标签页 → 选择打印配置档与份数；
2. 点 **Generate**，在跳转页下载 PDF 或 PNG；或在配置中设为浏览器直打；
3. 浏览器打印对话框中选择 **100% 缩放（不缩放）**。

标签内容编辑：配置页 → *Edit target* 选择全局或某配置档 → 增删行、设字号/样式/颜色/对齐 → 保存。

---

## 三、兼容性

| GLPI | 支持 | 备注 |
|---|---|---|
| 12.0.x | ✅ | v1.6.1 起完整支持（CJK 字体、Group 字段、rightname） |
| 11.x | ✅ | 自定义资产、tc-lib-barcode |
| 10.x | ✅ | QR 编码回退 TCPDF、`Session` 引导修复 |

PHP 7.4–8.4。支持资产类型：Computer / Monitor / Peripheral / Phone / Printer / NetworkEquipment / 打印机墨盒 / 自定义资产类别 / fields 插件自定义字段。

---

## 四、版本历史（本 Fork，相对上游 v1.5.1）

| 版本 | 主要改动 |
|---|---|
| 1.8.2 | 黑白模式颜色渲染与预览一致性修复 |
| 1.8.1 | 标签内容编辑器独立保存按钮 |
| 1.8.0 | 每个打印配置档独立标签内容 |
| 1.7.3 | 标签生成时间字段 `generated` |
| 1.7.2 | GLPI 12 Group 字段修复 + Technician group 字段 |
| 1.7.1 | fields 插件自定义字段接入 |
| 1.7.0 | 可配置标签内容、简体中文翻译、布局引擎重写 |
| 1.6.1 | 中文 CJK 字体、混合文本分字体渲染、TCPDF 沙箱路径 |
| 1.5.0 | GLPI 12 基础兼容、自定义资产、多项修复与安全加固 |

完整逐版本说明见 [`CHANGELOG.md`](CHANGELOG.md)，fork 改造摘要见 [`FORK_NOTES.md`](FORK_NOTES.md)。

---

## 五、许可证与致谢

- 本 Fork 遵循上游许可证 **AGPL-3.0**（见 [LICENSE](LICENSE)）。
- 上游原作者：**Etienne Gaillard** — https://github.com/tienou/glpi-qrcodelabel
- 内置字体：Noto Sans / Droid Sans Fallback（Apache 2.0，© Google LLC），详见 [`fonts/README.md`](fonts/README.md)。
