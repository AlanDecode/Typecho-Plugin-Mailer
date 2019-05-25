# Typecho Mailer Plugin

> 📧 又一款评论邮件提醒插件。

<mark>本插件需要使用 Typecho 开发版（17.11.15 以上版本）</mark>。插件支持自定义模板。使用 Typecho 最新的异步回调机制编写，基于 Joyqi 给出的 [Demo](https://joyqi.com/typecho/typecho-async-service.html)。

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

也可以单独设置是否提醒博主或者访客。待审或垃圾评论不会提醒访客。

## 使用

下载插件，上传至插件目录，后台启用后设置相关信息。然后在博客评论区 `form` 元素中合适位置添加：

```html
<span>
    <input name="receiveMail" type="checkbox" value="yes" checked />
    <label for="receiveMail"><strong>接收</strong>邮件通知</label>
</span>
```

以上代码必须添加，不添加不会发信。VOID 主题开发版已做了处理。

然后在插件设置页面填写发件信息。注意，如果你是用的 QQ 之类的邮箱，可能需要生成专用密码，而不能直接使用登陆密码。

插件默认提供了一个比较简单的发信模板，如果有好看的模板欢迎在 issue 区分享。模板中可以使用一些变量，见插件设置页说明。

## 日志

**2019.05.25**

* 修复后台回复不发信的问题（可能需要禁用后重新启用插件）

## License

MIT © [AlanDecode](https://github.com/AlanDecode)