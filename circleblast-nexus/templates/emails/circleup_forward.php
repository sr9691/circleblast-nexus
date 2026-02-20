<?php
defined('ABSPATH') || exit;
return [
	'subject' => '{{sender_name}} shared CircleBlast meeting notes with you',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi there,</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
<strong>{{sender_name}}</strong> thought you\'d find these meeting notes from CircleBlast interesting.</p>
' . '{{forward_note_block}}' . '
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0;">
<tr><td style="background-color:{{color_primary}};border-radius:6px;">
<a href="{{view_url}}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;">View Meeting Notes</a>
</td></tr></table>
<p style="font-size:13px;color:#a0aec0;">This link will expire in 30 days.</p>
<p style="font-size:15px;color:#333;">— The CircleBlast Team</p>',
];
