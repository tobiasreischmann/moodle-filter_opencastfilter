<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Opencast library functions.
 *
 * @package    filter
 * @subpackage opencast
 * @copyright  2017 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/lti/locallib.php');
require_once($CFG->dirroot . '/lib/oauthlib.php');

/**
 * Use lti to login and retrieve cookie from opencast.
 */
function filter_opencast_login() {
    global $PAGE;

    // Get api url of opencast.
    $endpoint = get_config('tool_opencast', 'apiurl');
    if (strpos($endpoint, 'http') !== 0) {
        $endpoint = 'http://' . $endpoint;
    }
    $endpoint .= '/lti';

    // Create parameters.
    $params = filter_opencast_create_parameters($endpoint);

    // Render form.
    $renderer = $PAGE->get_renderer('filter_opencast');
    echo $renderer->render_player($endpoint, $params);

    // Submit form.
    $PAGE->requires->js_call_amd('filter_opencast/form', 'init');
}

function filter_opencast_create_parameters() {
    global $CFG, $COURSE, $USER;

    // Get consumerkey and consumersecret.
    $consumerkey = get_config('filter_opencast', 'consumerkey');
    $consumersecret = get_config('filter_opencast', 'consumersecret');

    $helper = new oauth_helper(array('oauth_consumer_key'    => $consumerkey,
                                     'oauth_consumer_secret' => $consumersecret));

    // Set all necessary parameters.
    $params = array();
    $params['oauth_version'] = '1.0';
    $params['oauth_nonce'] = $helper->get_nonce();
    $params['oauth_timestamp'] = $helper->get_timestamp();
    $params['oauth_consumer_key'] = $consumerkey;
    $params['user_id'] = $USER->id;
    $params['roles'] = lti_get_ims_role($USER, null, $COURSE->id, false);
    $params['context_id'] = $COURSE->id;
    $params['context_label'] = trim($COURSE->shortname);
    $params['context_title'] = trim($COURSE->fullname);
    $params['resource_link_id'] = 'o' . random_int(1000, 9999) . '-' . random_int(1000, 9999);
    $params['resource_link_title'] = 'Opencast';
    $params['context_type'] = ($COURSE->format == 'site') ? 'Group' : 'CourseSection';
    $params['lis_person_name_given'] = $USER->firstname;
    $params['lis_person_name_family'] = $USER->lastname;
    $params['lis_person_name_full'] = $USER->firstname . ' ' . $USER->lastname;
    $params['ext_user_username'] = $USER->username;
    $params['lis_person_contact_email_primary'] = $USER->email;
    $params['launch_presentation_locale'] = current_language();
    $params['ext_lms'] = 'moodle-2';
    $params['tool_consumer_info_product_family_code'] = 'moodle';
    $params['tool_consumer_info_version'] = strval($CFG->version);
    $params['oauth_callback'] = 'about:blank';
    $params['lti_version'] = 'LTI-1p0';
    $params['lti_message_type'] = 'basic-lti-launch-request';
    $urlparts = parse_url($CFG->wwwroot);
    $params['tool_consumer_instance_guid'] = $urlparts['host'];

    if (!empty($CFG->mod_lti_institution_name)) {
        $params['tool_consumer_instance_name'] = trim(html_to_text($CFG->mod_lti_institution_name, 0));
    } else {
        $params['tool_consumer_instance_name'] = get_site()->shortname;
    }

    $params['launch_presentation_document_target'] = 'iframe';
    $params['oauth_signature_method'] = 'HMAC-SHA1';
    $params['oauth_signature'] = $helper->sign("POST", $endpoint, $params, $consumersecret . '&');

    return $params;
}