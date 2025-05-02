# [W.I.P]HuHoBot-PocketMineAdapter
[![GitHub Release](https://img.shields.io/github/v/release/HuHoBot/BDSAdapter?style=for-the-badge)](https://github.com/HuHoBot/BDSAdapter/releases)
[![License](https://img.shields.io/github/license/HuHoBot/BDSAdapter?style=for-the-badge)](https://github.com/HuHoBot/BDSAdapter/blob/main/LICENSE)

专为PocketMine-MP设计的下一代基岩版服务器管理解决方案，提供安全的无第三方QQ机器人依赖管理体验。

## 🌟 核心优势

| 特性     | 传统方案             | HuHoBot           |
|--------|------------------|-------------------|
| 账号安全   | ❌ 需要实体QQ号，存在封号风险 | ✅ 无QQ第三方客户端依赖，零风控 |
| 部署复杂度  | ❌ 需搭建完整机器人框架     | ✅ 即装即用，一键绑定       |
| 服务器兼容性 | ❌ 部分面板服不支持       | ✅ 全平台兼容，有网即用      |
| 协议更新影响 | ❌ 需要频繁适配新协议      | ✅ 协议无关设计，相对稳定     |
****
有关机器人详细内容请参阅[HuHobot组织主页面](https://github.com/HuHoBot/)

## 本插件已经实现的功能
- [x] 握手
- [x] 绑定
- [x] 查询在线
- [ ] 消息互通
- [ ] 服务器命令
- [ ] 自定义命令
- [ ] 白名单操作

## ⚙️ 配置示例

```yaml
---
# 服务器唯一ID (启动时自动生成)
# ! 请勿手动修改，留空即可
serverId: ~
# 通信加密密钥 (绑定后自动获取)
# ! 请勿手动修改，留空即可
hashKey: ~
# 服务器显示名称
serverName: PocketMine-MP Server
# MOTD服务器地址
# 格式: 地址:端口 (示例: play.easecation.net:19132)
motdUrl: play.easecation.net:19132
...

```
