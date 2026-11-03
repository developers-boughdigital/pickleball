<?php

namespace App\Support;

use App\Http\Requests\Admin\ClubRequest;
use App\Models\Club;

/** clubs.csv from the directory import pack. */
class ClubSpreadsheet extends DirectorySpreadsheet
{
    protected const MODEL = Club::class;

    protected const REQUEST = ClubRequest::class;

    public const NAME = 'Clubs';

    public const COLUMNS = [
        'ref', 'slug', 'name', 'suburb', 'postcode', 'council', 'city', 'state',
        'short_description', 'description', 'levels', 'session_types', 'session_times_text',
        'beginners_welcome', 'juniors', 'seniors', 'womens_sessions', 'equipment_provided', 'coaching_available',
        'come_and_try', 'dupr_play', 'pa_affiliated', 'state_affiliation',
        'membership_fee_cents', 'casual_fee_cents', 'court_hire_cents', 'membership_notes',
        'booking_method', 'booking_url', 'calendar_url', 'coach_names_text', 'leagues_text',
        'email', 'phone', 'website', 'facebook', 'instagram', 'tiktok', 'youtube',
        'year_established', 'member_count', 'enrichment_evidence',
        'source_type', 'source_url', 'record_status', 'checked_at', 'checked_by', 'profile_tier', 'claim_status', 'enriched_at', 'notes',
        'status',
    ];

    protected const BOOLEANS = [
        'beginners_welcome', 'juniors', 'seniors', 'womens_sessions', 'equipment_provided', 'coaching_available',
        'come_and_try', 'dupr_play', 'pa_affiliated',
    ];

    protected const NUMERIC = ['membership_fee_cents', 'casual_fee_cents', 'court_hire_cents', 'year_established', 'member_count', 'profile_tier'];

    protected const URLS = ['website', 'booking_url', 'calendar_url', 'facebook', 'instagram', 'tiktok', 'youtube'];

    protected const PIPE_LISTS = ['levels', 'session_types'];

    protected const CODED = ['booking_method', 'record_status', 'claim_status', 'status'];
}
