# 变更日志（本 Fork）

上游 [tienou/glpi-qrcodelabel](https://github.com/tienou/glpi-qrcodelabel) 止于 v1.5.1（仅 GLPI 10/11）。以下为本 Fork 的演进记录，上游原始 changelog 见 [UPSTREAM.md](UPSTREAM.md)。

## [1.8.2]
- **Fix** — 打印颜色与预览一致：黑白模式改为调色板灰度渲染（主黑/#666/#333/#999），纯黑白请用单色模式；预览采用当前编辑项的实际颜色模式（含反色模式）。

## [1.8.1]
- **Fix** — 标签内容编辑器增加独立"保存标签内容"按钮；当编辑器目标为某打印配置档时，配置档保存按钮也会一并保存编辑行（此前静默丢弃）。

## [1.8.0]
- **New** — 每个打印配置档可存自己的标签内容行；生成时跟随所选配置档，未配置者继承全局内容。

## [1.7.3]
- **New** — 标签生成时间字段 `generated`：打印批次生成时刻 `DD/MM/YYYY HH:MM`。

## [1.7.2]
- **Fix** — GLPI 12 下 Group 字段打印为空：资产-群组关系已从 `groups_id` 列迁移至 `glpi_groups_items` 表，两种布局均支持，多群组合并以逗号连接；新增 Technician group 字段。

## [1.7.1]
- **New** — Additional fields（fields 插件）自定义字段自动进入标签内容可选列表（键 `af_c<container>_f<field>`）；支持文本/数字/日期/是否/dropdown 类型，不适用的字段打印时跳过。

## [1.7.0]
- **New** — 可配置标签内容：任意字段、顺序、字号、样式、颜色、对齐，实时预览与恢复默认。
- **New** — 简体中文翻译（`locales/zh_CN.po/.mo`）。
- 布局引擎重写为单一纵向流，不再固定顶/底块。

## [1.6.1]
- **Fix** — 中文字符打印为空方框：内置 Droid Sans Fallback、CJK/Latin 混合文本分字体渲染、`K_ALLOWED_PATHS` 让 TCPDF 12 沙箱可读取导入的字体定义。

## [1.5.0]
- **Fix (#4)** — GLPI 10 下插件页 HTTP 500（`Class "Session" not found`）：补齐 `front/` 引导。
- **Fix** — GLPI 10 标签生成失败：`tc-lib-barcode` 仅随 GLPI 11 提供，回退到 TCPDF 自带 QR 编码器。
- **Fix (#3)** — QR 标签页自 1.2.0 起未注册；生成后跳转保留下载按钮的页面；恢复"份数"（PDF）。
- **Fix (#2)** — 24/25 mm 标签字段补全、36 mm 位置/所有者文字重叠修复。
- **New (#1)** — GLPI 11/12 自定义资产：每个活动资产定义获得标签页与批量动作。
- **Security** — 实体读取权限校验、配置档权限隔离、配置页 HTML 转义、logo 重编码为真实 PNG、临时文件按用户隔离。
- **GLPI 12** — 版本范围扩展至 12.0.99；rightname 运行时赋值、csrf 分支、Firewall 路由适配。
