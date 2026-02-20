<?php
/**
 * Migration: 020 - Seed recruitment categories
 *
 * Populates cb_recruitment_categories with the 29 standard roles
 * the group needs to fill. Safe to run on existing installs —
 * skips seeding if the table already contains rows.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_020_Seed_Recruitment_Categories {

	public static function up(): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'cb_recruitment_categories';

		// Only seed if the table is empty (don't overwrite admin customizations).
		$count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
		if ($count > 0) {
			return true;
		}

		$now      = gmdate('Y-m-d H:i:s');
		$admin_id = get_current_user_id() ?: 1;

		$categories = [
			['Commercial Banker',            'Manages business lending and credit portfolios.',                'Finance',           'high',   1],
			['Payroll Service Provider',      'Handles automated payroll and tax compliance.',                  'Business Services', 'medium', 1],
			['HR Consultant',                 'Advises on HR strategy and employee relations.',                 'Human Resources',   'medium', 1],
			['Expense Reduction Analyst',     'Identifies cost-saving opportunities in operations.',            'Consulting',        'low',    1],
			['EOS Implementer',               'Facilitates EOS for leadership team alignment.',                 'Consulting',        'high',   1],
			['Software Developer',            'Develops and maintains custom software solutions.',              'Technology',        'medium', 1],
			['Recruiter',                     'Sources and screens talent for open positions.',                 'Human Resources',   'medium', 1],
			['Executive Search',              'Specialized recruitment for C-suite roles.',                     'Human Resources',   'high',   1],
			['Staffing Consultant',           'Provides contract and temporary staffing solutions.',            'Business Services', 'medium', 1],
			['Website Developer',             'Designs and builds responsive websites.',                        'Technology',        'medium', 1],
			['Social Media Specialist',       'Manages social media content and engagement.',                   'Marketing',         'low',    1],
			['PR Specialist',                 'Handles public relations and media outreach.',                   'Marketing',         'medium', 1],
			['Commercial Real Estate',        'Facilitates commercial property sales and leases.',              'Real Estate',       'high',   1],
			['Credit Card Processing',        'Provides merchant services and payment tech.',                   'Finance',           'low',    1],
			['Office Furniture Consultant',   'Advises on workspace design and furniture.',                     'Design',            'low',    1],
			['Janitorial Cleaning',           'Provides daily cleaning for office spaces.',                     'Facilities',        'medium', 1],
			['Commercial Cleaning',           'Industrial-grade cleaning for large facilities.',                'Facilities',        'medium', 1],
			['Building Security Provider',    'Installs security systems and monitoring.',                      'Security',          'high',   1],
			['Facilities Manager',            'Coordinates building maintenance and ops.',                      'Facilities',        'medium', 1],
			['Property Manager',              'Manages commercial assets and tenant needs.',                    'Real Estate',       'high',   1],
			['Corporate Event Planner',       'Plans and executes professional corporate events.',              'Events',            'medium', 1],
			['Branded Apparel',               'Supplies custom branded clothing and uniforms.',                 'Marketing',         'low',    1],
			['Trade Show Gifts',              'Provides promotional items for exhibitions.',                    'Marketing',         'low',    1],
			['Sales Trainer',                 'Delivers training to improve sales performance.',                'Consulting',        'medium', 1],
			['Commercial Security',           'Provides security personnel and risk assessment.',               'Security',          'high',   1],
			['Country Club Director',         'Manages hospitality and operations at clubs.',                   'Hospitality',       'medium', 1],
			['Golf Club Director',            'Directs golf operations and tournament events.',                 'Hospitality',       'medium', 1],
			['Architect',                     'Designs blueprints for commercial buildings.',                   'Architecture',      'high',   1],
			['Engineer',                      'Provides engineering support for construction.',                 'Engineering',       'high',   1],
		];

		$sort = 1;
		foreach ($categories as $cat) {
			$wpdb->insert($table, [
				'title'        => $cat[0],
				'description'  => $cat[1],
				'industry'     => $cat[2],
				'priority'     => $cat[3],
				'target_count' => $cat[4],
				'is_filled'    => 0,
				'sort_order'   => $sort,
				'created_by'   => $admin_id,
				'created_at'   => $now,
				'updated_at'   => $now,
			]);
			$sort++;
		}

		// Verify at least some rows were inserted.
		$inserted = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
		return $inserted >= 25;
	}
}
