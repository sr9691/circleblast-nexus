<?php
/**
 * Email Template: Recruitment - Visit Thank You + Feedback
 *
 * Sent to a candidate when they are moved to the "Visited" stage.
 * Includes a thank-you and asks for their feedback on the experience.
 */

defined('ABSPATH') || exit;

return [
	'subject' => 'Thanks for Visiting CircleBlast, {{candidate_first_name}}!',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{candidate_first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Thank you for visiting CircleBlast! We loved having you at our meeting and hope you enjoyed getting to know the group.</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
We\'d love to hear your thoughts on the experience. Your honest feedback helps us keep improving and helps us understand whether CircleBlast might be the right community for you.</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 8px;"><strong>A few quick questions:</strong></p>
<ol style="font-size:15px;color:#333;line-height:1.8;margin:0 0 20px;padding-left:20px;">
<li>What was your overall impression of the group?</li>
<li>Did you make any connections that felt valuable?</li>
<li>Is there anything we could do differently to improve the experience for visitors?</li>
<li>Would you be interested in learning more about membership?</li>
</ol>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Simply reply to this email with your thoughts — no formal survey needed. We read every response.</p>
{{visit_notes_block}}
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 8px;">Thanks again for spending time with us. We hope to see you again soon!</p>
<p style="font-size:15px;color:#333;margin:0;">— The CircleBlast Team</p>',
];
