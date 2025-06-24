# HuHoBot-PocketMineAdapter
[![GitHub Release](https://img.shields.io/github/v/release/Sunch233/HuHoBot-PocketMineAdapter?style=for-the-badge)](https://github.com/Sunch233/HuHoBot-PocketMineAdapter/releases)
[![License](https://img.shields.io/github/license/Sunch233/HuHoBot-PocketMineAdapter?style=for-the-badge)](https://github.com/Sunch233/HuHoBot-PocketMineAdapter/blob/main/LICENSE)

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

## 插件特点
- 使用多线程处理网络io，防止网络卡顿阻塞主线程
- events系统分类每一种功能的处理
- 可高度自定义的执行命令API

## 如何制作自定义命令？
1. 创建新插件, 监听`HuHoBot\customCommand\RunCustomCommandEvent`
2. 通过 `getCommand` `getArgs` 等api获取指令信息
3. 使用 `setResponseMessage` 设置回复消息
4. 大功告成

## ⚙️ 配置示例
- `/huho reload` 重载配置文件
- `/huho reconnect` 手动重新连接机器人


```yaml
---
# 是否安全连接（连不上服务器可选择关闭）
safeConnect: true
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
motdUrl: play.easecation.net:19132duo
# 启用群聊消息互通
enableGroupChat: false
# 群聊消息格式 (可用变量: {nick}, {msg})
chatFormatGroup: 群:<{nick}> {msg}
# 游戏消息格式 (可用变量: {name}, {msg})
chatFormatGame: <{name}> {msg}
#  游戏消息转发前缀
chatFormatGamePrefix: '#'
...

```
