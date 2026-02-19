<?php
/**
 * Email Template: Recruitment Stage Update (Referrer Notification)
 *
 * Sent to the referring member when their candidate's pipeline stage changes.
 */

defined('ABSPATH') || exit;

return [
	'subject' => 'Recruitment Update: {{candidate_name}} moved to {{stage_label}}',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{referrer_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Your referral <strong>{{candidate_name}}</strong>{{candidate_company_line}} has been moved to the <strong>{{stage_label}}</strong> stage in our recruitment pipeline.</p>
{{stage_detail_block}}
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Thank you for helping CircleBlast grow with great people. We\'ll keep you posted on further progress.</p>
<p style="font-size:15px;color:#333;margin:0;">— The CircleBlast Team</p>',
];
