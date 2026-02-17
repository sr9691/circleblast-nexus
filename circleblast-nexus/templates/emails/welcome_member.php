<?php
/**
 * Email Template: Welcome Member
 *
 * ITER-0005: Sent when an admin creates a new member account.
 */

defined('ABSPATH') || exit;

return [
	'subject' => 'Welcome to CircleBlast, {{first_name}}!',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Welcome to <strong>CircleBlast</strong>! We\'re excited to have you join our professional networking community.</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Your account has been created. Here\'s how to get started:</p>
<ol style="font-size:15px;color:#333;line-height:1.8;margin:0 0 20px;padding-left:20px;">
<li><strong>Set your password</strong> using the link below</li>
<li><strong>Complete your profile</strong> — add your expertise and how you can help others</li>
<li><strong>Browse the directory</strong> to discover members</li>
<li><strong>Request a 1:1</strong> with someone who shares your interests</li>
</ol>
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0;">
<tr><td style="background-color:#2563eb;border-radius:6px;">
<a href="{{login_url}}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;">Set Your Password &amp; Log In</a>
</td></tr></table>
<p style="font-size:13px;color:#6c757d;word-break:break-all;margin:0 0 20px;">{{login_url}}</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 8px;">See you at the next CircleUp!</p>
<p style="font-size:15px;color:#333;margin:0;">— The CircleBlast Team</p>',
];
