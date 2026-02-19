<?php
/**
 * Email Template: Member Reactivation
 *
 * Sent when an admin reactivates a member from inactive or alumni status.
 * Includes a password reset link so they can regain access.
 */

defined('ABSPATH') || exit;

return [
	'subject' => 'Welcome back to CircleBlast, {{first_name}}!',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Great news — your <strong>CircleBlast</strong> membership has been reactivated! We&rsquo;re glad to have you back.</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Here&rsquo;s what you need to know:</p>
<ol style="font-size:15px;color:#333;line-height:1.8;margin:0 0 20px;padding-left:20px;">
<li><strong>Reset your password</strong> using the button below to regain access</li>
<li><strong>Update your profile</strong> — make sure your expertise and contact info are current</li>
<li><strong>Check the directory</strong> — new members may have joined while you were away</li>
<li><strong>Request a 1:1</strong> to reconnect with the group</li>
</ol>
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0;">
<tr><td style="background-color:#2563eb;border-radius:6px;">
<a href="{{login_url}}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;">Reset Password &amp; Log In</a>
</td></tr></table>
<p style="font-size:13px;color:#6c757d;word-break:break-all;margin:0 0 20px;">{{login_url}}</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 8px;">Looking forward to seeing you at the next CircleUp!</p>
<p style="font-size:15px;color:#333;margin:0;">— The CircleBlast Team</p>',
];
