<?php
/**
 * Migration: 003 - Register member meta schema
 *
 * ITER-0004: Defines the 17 custom usermeta keys used for member profiles.
 * This migration does not create database tables — it registers a
 * wp_option containing the meta key definitions so the application
 * has a single source of truth for the member profile schema.
 *
 * The actual usermeta values are stored in WordPress's native wp_usermeta
 * table using update_user_meta() / get_user_meta().
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_003_Register_Member_Meta {

	/**
	 * Option key where the schema definition is stored.
	 */
	public const OPTION_KEY = 'cbnexus_member_meta_schema';

	/**
	 * Option key for the industry taxonomy.
	 */
	public const INDUSTRY_OPTION_KEY = 'cbnexus_industry_taxonomy';

	/**
	 * Apply migration.
	 *
	 * @return bool True on success.
	 */
	public static function up(): bool {
		$schema = self::get_meta_schema();
		update_option(self::OPTION_KEY, $schema, false);

		$industries = self::get_industry_taxonomy();
		update_option(self::INDUSTRY_OPTION_KEY, $industries, false);

		// Validate.
		$saved = get_option(self::OPTION_KEY, []);
		return is_array($saved) && count($saved) === 17;
	}

	/**
	 * Define all 17 custom usermeta keys with their validation rules.
	 *
	 * Each key maps to a definition array with:
	 *   - label:    Human-readable field name
	 *   - type:     Data type (string, text, email, url, date, select, tags)
	 *   - required: Whether the field is required on member creation
	 *   - editable_by_member: Whether members can edit this field themselves
	 *   - section:  UI grouping for admin/edit forms
	 *
	 * @return array<string, array>
	 */
	private static function get_meta_schema(): array {
		return [
			'cb_company' => [
				'label'              => 'Company',
				'type'               => 'string',
				'required'           => true,
				'editable_by_member' => true,
				'section'            => 'professional',
				'max_length'         => 200,
			],
			'cb_title' => [
				'label'              => 'Job Title',
				'type'               => 'string',
				'required'           => true,
				'editable_by_member' => true,
				'section'            => 'professional',
				'max_length'         => 200,
			],
			'cb_industry' => [
				'label'              => 'Industry',
				'type'               => 'select',
				'required'           => true,
				'editable_by_member' => true,
				'section'            => 'professional',
				'options_source'     => 'cbnexus_industry_taxonomy',
			],
			'cb_expertise' => [
				'label'              => 'Expertise / Skills',
				'type'               => 'tags',
				'required'           => false,
				'editable_by_member' => true,
				'section'            => 'networking',
				'max_tags'           => 10,
			],
			'cb_looking_for' => [
				'label'              => 'Looking For',
				'type'               => 'tags',
				'required'           => false,
				'editable_by_member' => true,
				'section'            => 'networking',
				'max_tags'           => 10,
			],
			'cb_can_help_with' => [
				'label'              => 'Can Help With',
				'type'               => 'tags',
				'required'           => false,
				'editable_by_member' => true,
				'section'            => 'networking',
				'max_tags'           => 10,
			],
			'cb_phone' => [
				'label'              => 'Phone Number',
				'type'               => 'string',
				'required'           => false,
				'editable_by_member' => true,
				'section'            => 'contact',
				'max_length'         => 30,
			],
			'cb_linkedin' => [
				'label'              => 'LinkedIn URL',
				'type'               => 'url',
				'required'           => false,
				'editable_by_member' => true,
				'section'            => 'contact',
				'max_length'         => 500,
			],
			'cb_website' => [
				'label'              => 'Website',
				'type'               => 'url',
				'required'           => false,
				'editable_by_member' => true,
				'section'            => 'contact',
				'max_length'         => 500,
			],
			'cb_bio' => [
				'label'              => 'Bio / About',
				'type'               => 'text',
				'required'           => false,
				'editable_by_member' => true,
				'section'            => 'personal',
				'max_length'         => 2000,
			],
			'cb_photo_url' => [
				'label'              => 'Profile Photo URL',
				'type'               => 'url',
				'required'           => false,
				'editable_by_member' => true,
				'section'            => 'personal',
				'max_length'         => 500,
			],
			'cb_referred_by' => [
				'label'              => 'Referred By',
				'type'               => 'string',
				'required'           => false,
				'editable_by_member' => false,
				'section'            => 'admin',
				'max_length'         => 200,
			],
			'cb_join_date' => [
				'label'              => 'Join Date',
				'type'               => 'date',
				'required'           => true,
				'editable_by_member' => false,
				'section'            => 'admin',
			],
			'cb_member_status' => [
				'label'              => 'Member Status',
				'type'               => 'select',
				'required'           => true,
				'editable_by_member' => false,
				'section'            => 'admin',
				'options'            => ['active', 'inactive', 'alumni'],
			],
			'cb_onboarding_stage' => [
				'label'              => 'Onboarding Stage',
				'type'               => 'select',
				'required'           => false,
				'editable_by_member' => false,
				'section'            => 'admin',
				'options'            => ['access_setup', 'walkthrough', 'ignite', 'ambassador', 'complete'],
			],
			'cb_ambassador_id' => [
				'label'              => 'Ambassador (Member ID)',
				'type'               => 'string',
				'required'           => false,
				'editable_by_member' => false,
				'section'            => 'admin',
				'max_length'         => 20,
			],
			'cb_notes_admin' => [
				'label'              => 'Admin Notes',
				'type'               => 'text',
				'required'           => false,
				'editable_by_member' => false,
				'section'            => 'admin',
				'max_length'         => 5000,
			],
		];
	}

	/**
	 * Default industry taxonomy for the cb_industry select field.
	 *
	 * @return string[]
	 */
	private static function get_industry_taxonomy(): array {
		return [
			'Accounting & Finance',
			'Agriculture',
			'Architecture & Design',
			'Automotive',
			'Banking & Financial Services',
			'Biotechnology',
			'Business Consulting',
			'Construction',
			'Education',
			'Energy & Utilities',
			'Engineering',
			'Entertainment & Media',
			'Environmental Services',
			'Food & Beverage',
			'Government & Public Sector',
			'Healthcare',
			'Hospitality & Tourism',
			'Human Resources',
			'Information Technology',
			'Insurance',
			'Legal',
			'Logistics & Supply Chain',
			'Manufacturing',
			'Marketing & Advertising',
			'Nonprofit & Social Enterprise',
			'Pharmaceuticals',
			'Real Estate',
			'Retail & E-Commerce',
			'Software & SaaS',
			'Sports & Recreation',
			'Telecommunications',
			'Transportation',
			'Venture Capital & Private Equity',
			'Other',
		];
	}
}
