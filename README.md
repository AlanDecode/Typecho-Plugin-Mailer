# Typecho Mailer Plugin

> 📧 又一款评论邮件提醒插件。

<mark>目前本插件需要使用 Typecho 最新开发版</mark>。插件支持自定义模板。使用 Typecho 最新的异步回调机制编写，基于 Joyqi 给出的 [Demo](https://joyqi.com/typecho/typecho-async-service.html)。

发信规则如下：

| 评论者 |   被评论对象   |      发信规则      |
| :----: | :------------: | :----------------: |
|  博主  |      文章      |       不发信       |
|  博主  |      博主      |       不发信       |
|  博主  |      访客      |      提醒访客      |
|  访客  |      文章      |      提醒博主      |
|  访客  |      博主      |      提醒博主      |
|  访客  |  访客（本人）  |      提醒博主      |
|  访客  | 访客（非本人） | 提醒评论对象与博主 |

## 使用

下载插件，上传至插件目录，后台启用后设置相关信息。然后在博客评论区 `form` 元素中合适位置添加：

```html
<span>
    <input aria-label="接收邮件通知" name="receiveMail" type="checkbox" value="yes" id="receiveMail" checked />
    <label for="receiveMail"><strong>接收</strong>邮件通知</label>
</span>
```

## License

MIT © [AlanDecode](https://github.com/AlanDecode)