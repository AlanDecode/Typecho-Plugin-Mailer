<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __DIR__ . '/PHPMailer.php';

/**
 * 将评论发送至相关邮箱
 * 
 * @package     Mailer
 * @author      熊猫小A
 * @version     1.0.1
 * @dependence  17.11.15
 * @link        https://blog.imalan.cn/archives/349/
 */
class Mailer_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return string
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array('Mailer_Plugin', 'requestService');
        Typecho_Plugin::factory('Widget_Comments_Edit')->finishComment = array('Mailer_Plugin', 'requestService');
        Typecho_Plugin::factory('Widget_Service')->sendMail = array('Mailer_Plugin', 'sendMail');

        // 添加一列，存储是否接受回复提醒
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        if (!array_key_exists('receiveMail', $db->fetchRow($db->select()->from('table.comments'))))
            $db->query('ALTER TABLE `'. $prefix .'comments` ADD COLUMN `receiveMail` INT(10) DEFAULT 1;');

        return '请记得进入插件配置相关信息。';
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){}
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text('host', NULL, '', '邮件服务器'));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Select('port', array(25 => 25, 465 => 465, 587 => 587, 2525 => 2525), 587, '端口号'));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Select('secure', array('tls' => 'tls', 'ssl' => 'ssl'), 'ssl', '连接加密方式'));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio('auth', array(1 => '是', 0 => '否'), 0, '启用身份验证'));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text('user', NULL, '', '用户名', '启用身份验证后有效'));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text('password', NULL, '', '密码', '启用身份验证后有效'));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text('from', NULL, '', '发送人邮箱'));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio('notifyBlogger', array(1 => '是', 0 => '否'), 1, '提醒博主', '是否提醒博主。'));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio('notifyGuest', array(1 => '是', 0 => '否'), 1, '提醒访客', '是否提醒访客（可以的话）。'));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio('synchronizedSend', array(0 => '否', 1 => '是'), 0, '强制同步发送', '强制不使用异步发送。如果频繁出现丢信的情况，可以打开这个选项。一般不用开启。'));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Textarea('templateHost', NULL, file_get_contents(__DIR__.'/templateHost.html'), '向博主发信内容模板。', '模板中可以使用某些变量，变量需要使用 {{}} 括起来。
            可用的变量有：post_title，post_permalink，post_author_name，post_author_mail，comment_content，comment_permalink，comment_author_name，comment_author_mail，comment_parent_content，comment_parent_author_name，comment_parent_author_mail，status，ip，site_url，site_name，manage_url'));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Textarea('templateGuest', NULL, file_get_contents(__DIR__.'/templateGuest.html'), '向访客发信内容模板。', '模板中可以使用某些变量，变量需要使用 {{}} 括起来。
            可用的变量有：post_title，post_permalink，post_author_name，post_author_mail，comment_content，comment_permalink，comment_author_name，comment_author_mail，comment_parent_content，comment_parent_author_name，comment_parent_author_mail，status，ip，site_url，site_name，manage_url'));
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    /**
     * 检查参数
     *
     * @param array $settings
     * @return string
     */
    public static function configCheck(array $settings)
    {
        if (!empty($settings['host'])) {
            $smtp = new SMTP;
            $smtp->setTimeout(10);

            if (!$smtp->connect($settings['host'], $settings['port'])) {
                return '邮件服务器连接失败';
            }

            if (!$smtp->hello(gethostname())) {
                return '向邮件服务器发送指令失败';
            }

            $e = $smtp->getServerExtList();

            if (is_array($e) && array_key_exists('STARTTLS', $e)) {
                if ($settings['secure'] != 'tls') {
                    return '邮件服务器要求使用TLS加密';
                }

                $tls = $smtp->startTLS();

                if (!$tls) {
                    return '无法用TLS连接邮件服务器';
                }

                if (!$smtp->hello(gethostname())) {
                    return '向邮件服务器发送指令失败';
                }

                $e = $smtp->getServerExtList();
            }

            if (is_array($e) && array_key_exists('AUTH', $e)) {
                if (!$settings['auth']) {
                    return '邮件服务器要求启用身份验证';
                }

                if (!$smtp->authenticate($settings['user'], $settings['password'])) {
                    return '身份验证失败, 请检查您的用户名或者密码';
                }
            }
        }
    }
    
    /**
     * 异步回调
     * 
     * @access public
     * @param int $commentId 评论id
     * @return void
     */
    public static function sendMail($commentId)
    {
        $options = Helper::options();
        $pluginOptions = $options->plugin('Mailer');

        if (empty($pluginOptions->host)) {
            return;
        }

        $commentObj = self::widgetById('comments', $commentId);
        $parentCommentObj = '';
        if($commentObj->parent) {
            $parentCommentObj = self::widgetById('comments', $commentObj->parent);
        }
        $postObj = self::widgetById('contents', $commentObj->cid);

        if (!$commentObj->have()) {
            return;
        }

        $stausArr = array('approved' => '已通过',
            'waiting' => '待审',
            'spam' => '垃圾评论');
        
        // 向博主发信。若评论发起者为博主，不用发信
        if ($pluginOptions->notifyBlogger && $commentObj->authorId != $postObj->authorId) {
            $mailTo = $postObj->author->mail;
            $name = $postObj->author->screenName;
            $subject = '《'.$postObj->title.'》一文有新评论啦！';
            $body = str_replace(
                array('{{post_title}}', '{{post_permalink}}', '{{post_author_name}}', '{{post_author_mail}}', 
                    '{{comment_content}}', '{{comment_permalink}}', '{{comment_author_name}}', '{{comment_author_mail}}', 
                    '{{status}}', '{{ip}}',
                    '{{site_name}}', '{{site_url}}', '{{manage_url}}'),
                array($postObj->title, $postObj->permalink, $postObj->author->screenName, $postObj->author->mail,
                    $commentObj->text, $commentObj->permalink, $commentObj->author, $commentObj->mail,
                    $stausArr[$commentObj->status], $commentObj->ip,
                    $options->title, $options->siteUrl, $options->adminUrl.'manage-comments.php'),
                $pluginOptions->templateHost);
            if (!empty($parentCommentObj)) {
                $body = str_replace(
                    array('{{comment_parent_content}}', '{{comment_parent_author_name}}', '{{comment_parent_author_mail}}'),
                    array($parentCommentObj->text, $parentCommentObj->author->name, $parentCommentObj->mail),
                    $body
                );
            } else {
                $body = str_replace(
                    array('{{comment_parent_content}}', '{{comment_parent_author_name}}', '{{comment_parent_author_mail}}'),
                    array('', '', ''),
                    $body
                );
            }

            self::send($mailTo, $name, $subject, $body);
        }

        // 向回复对象发信
        if ($pluginOptions->notifyGuest) {
            if (empty($parentCommentObj) || empty($parentCommentObj->mail)) return;
            if ($parentCommentObj->authorId == $postObj->authorId) return; // 回复对象是博主，不要再次发信
            if ($parentCommentObj->mail == $commentObj->mail) return; // 自己回复自己，不用提醒
            if ($commentObj->status != 'approved') return; // 只提醒过审评论

            $db = Typecho_Db::get();
            $row = $db->fetchRow($db->select('receiveMail')
                    ->from('table.comments')
                    ->where('coid = ?', $parentCommentObj->coid));
            if (!$row['receiveMail']) return; // 拒绝接收提醒

            $mailTo = $parentCommentObj->mail;
            $name = $parentCommentObj->author;
            $subject = '您在《'.$postObj->title.'》一文的评论有新回复啦！';
            $body = str_replace(
                array('{{post_title}}', '{{post_permalink}}', '{{post_author_name}}', '{{post_author_mail}}', 
                    '{{comment_content}}', '{{comment_permalink}}', '{{comment_author_name}}', '{{comment_author_mail}}',
                    '{{comment_parent_content}}', '{{comment_parent_author_name}}', '{{comment_parent_author_mail}}',
                    '{{status}}', '{{ip}}',
                    '{{site_name}}', '{{site_url}}', '{{manage_url}}'),
                array($postObj->title, $postObj->permalink, $postObj->author->screenName, $postObj->author->mail,
                    $commentObj->text, $commentObj->permalink, $commentObj->author, $commentObj->mail,
                    $parentCommentObj->text, $parentCommentObj->author, $parentCommentObj->mail,
                    $stausArr[$commentObj->status], $commentObj->ip,
                    $options->title, $options->siteUrl, $options->adminUrl.'manage-comments.php'),
                $pluginOptions->templateGuest);

            self::send($mailTo, $name, $subject, $body);
        }
    }

    /**
     * 发信
     * 
     * @access public
     * @param string $addr 收件地址
     * @param string $name 收件人名称
     * @param string $subject 邮件标题
     * @param string $body 邮件正文
     * @return void
     */
    public static function send($addr, $name, $subject, $body) {
        $options = Helper::options();
        $pluginOptions = $options->plugin('Mailer');
        
        $mail = new PHPMailer(false);
        $mail->isSMTP();
        $mail->isHTML(true);
        $mail->Host = $pluginOptions->host;
        $mail->SMTPAuth = !!$pluginOptions->auth;
        $mail->Username = $pluginOptions->user;
        $mail->Password = $pluginOptions->password;
        $mail->SMTPSecure = $pluginOptions->secure;
        $mail->Port = $pluginOptions->port;
        $mail->getSMTPInstance()->setTimeout(10);
        $mail->CharSet = 'utf-8';
        $mail->setFrom($pluginOptions->from, $options->title);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->addAddress($addr, $name);

        $mail->send();
    }

    /**
     * 评论回调，调起异步服务
     *
     * @param $comment
     */
    public static function requestService($comment)
    {
        if ($comment instanceof Widget_Feedback) {
            $r = 0;
            // 当前评论是否接受回复提醒
            if (isset($_POST['receiveMail']) && 'yes' == $_POST['receiveMail']) {
                $r = 1;
            }
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $db->query($db->update('table.comments')
                ->rows(array('receiveMail' => (int)$r))
                ->where('coid = ?', $comment->coid));
        }

        $options = Helper::options();
        $pluginOptions = $options->plugin('Mailer');

        if ($pluginOptions->synchronizedSend) {
            self::sendMail($comment->coid);
        } else {
            Helper::requestService('sendMail', $comment->coid);
        }        
    }

    /**
     * 根据ID获取单个Widget对象
     *
     * @param string $table 表名, 支持 contents, comments, metas, users
     * @return Widget_Abstract
     */
    public static function widgetById($table, $pkId)
    {
        $table = ucfirst($table);
        if (!in_array($table, array('Contents', 'Comments', 'Metas', 'Users'))) {
            return NULL;
        }

        $keys = array(
            'Contents'  =>  'cid',
            'Comments'  =>  'coid',
            'Metas'     =>  'mid',
            'Users'     =>  'uid'
        );

        $className = "Widget_Abstract_{$table}";
        $key = $keys[$table];
        $db = Typecho_Db::get();
        $widget = new $className(Typecho_Request::getInstance(), Typecho_Widget_Helper_Empty::getInstance());
        
        $db->fetchRow(
            $widget->select()->where("{$key} = ?", $pkId)->limit(1),
                array($widget, 'push'));

        return $widget;
    }
}
