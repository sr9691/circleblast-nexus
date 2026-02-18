<?php
defined('ABSPATH') || exit;
return [
	'subject' => 'CircleUp Recap: {{title}}',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Here\'s the recap from our latest CircleUp: <strong>{{title}}</strong> ({{meeting_date}}).</p>

<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin:16px 0;">
<table role="presentation" cellspacing="0" cellpadding="0" width="100%"><tr>
<td style="text-align:center;padding:8px;"><span style="font-size:24px;font-weight:700;color:#c49a3c;">{{wins_count}}</span><br/><span style="font-size:12px;color:#666;">Wins</span></td>
<td style="text-align:center;padding:8px;"><span style="font-size:24px;font-weight:700;color:#5b2d6e;">{{insights_count}}</span><br/><span style="font-size:12px;color:#666;">Insights</span></td>
<td style="text-align:center;padding:8px;"><span style="font-size:24px;font-weight:700;color:#2563eb;">{{actions_count}}</span><br/><span style="font-size:12px;color:#666;">Actions</span></td>
</tr></table>
</div>

{{summary_text}}

<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0;"><tr>
<td style="background-color:#5b2d6e;border-radius:6px;margin-right:12px;">
<a href="{{view_url}}" style="display:inline-block;padding:12px 24px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;">View Full Notes</a>
</td>
<td style="width:12px;"></td>
<td style="background-color:#e2e8f0;border-radius:6px;">
<a href="{{forward_url}}" style="display:inline-block;padding:12px 24px;color:#4a5568;text-decoration:none;font-size:15px;font-weight:500;">Forward to Someone</a>
</td>
</tr></table>

<p style="font-size:15px;color:#333;line-height:1.6;">Have a win or insight to share? <a href="{{quick_share_url}}" style="color:#5b2d6e;font-weight:600;">Share it now</a> — no login required.</p>

{{action_items_block}}

<p style="font-size:15px;color:#333;margin:16px 0 0;">— The CircleBlast Team</p>',
];
