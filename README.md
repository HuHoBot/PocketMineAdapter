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
- 使用独立线程处理网络 IO，防止网络卡顿阻塞主线程
- events系统分类每一种功能的处理
- 可高度自定义的执行命令API

## 如何制作自定义命令？
1. 创建新插件, 监听`HuHoBot\customCommand\RunCustomCommandEvent`
2. 通过 `getCommand` `getArgs` 等api获取指令信息
3. 使用 `setResponseMessage` 设置回复消息
4. 大功告成

QQ 群成员变动也会发布该事件。`getCommand()` 为 `#MemberAdd` 或 `#MemberRemove`，`getData()` 可获取完整协议 body，`getPackId()` 可获取消息 ID。

## 如何使用

- 扔到你的各种兼容pm api5的pmmp分支版本的plugin文件夹里
- 启动一次服务端生成配置文件，文件夹`HuHoBot`
- 更改配置文件，定义ws后端地址，motd服务(可选)等等
- 跟随提示绑定
- 正常使用
