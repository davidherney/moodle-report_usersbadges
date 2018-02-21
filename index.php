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
 * A report to display the users badges
 *
 * @package    report_usersbadges
 * @copyright 2018 David Herney Bernal - cirano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once '../../config.php';
require_once $CFG->libdir . '/adminlib.php';
require_once 'locallib.php';
require_once 'filters/lib.php';
require_once 'filters/filter_forms.php';

$sort           = optional_param('sort', 'u.firstname', PARAM_ALPHANUM);
$dir            = optional_param('dir', 'ASC', PARAM_ALPHA);
$page           = optional_param('page', 0, PARAM_INT);
$perpage        = optional_param('perpage', 30, PARAM_INT);
$format         = optional_param('format', '', PARAM_ALPHA);

admin_externalpage_setup('reportusersbadges', '', null, '', array('pagelayout'=>'report'));

$baseurl = new moodle_url('/report/usersbadges/index.php', array('sort' => $sort, 'dir' => $dir, 'perpage' => $perpage));

// create the user filter form
$filtering = new report_usersbadges_filtering();

list($extrasql, $params) = $filtering->get_sql_filter();

if ($format) {
    $perpage = 0;
}

$context = context_system::instance();
$site = get_site();

$columns = array('firstname', 'lastname', 'email');

foreach ($columns as $column) {
    $string[$column] = get_user_field_name($column);
    if ($sort != $column) {
        $columnicon = "";
        $columndir = "ASC";
    } else {
        $columndir = $dir == "ASC" ? "DESC":"ASC";
        $columnicon = ($dir == "ASC") ? "sort_asc" : "sort_desc";
        $columnicon = "<img class='iconsort' src=\"" . $OUTPUT->pix_url('t/' . $columnicon) . "\" alt=\"\" />";

    }
    $$column = '<a href="index.php?sort=' . $column . '&amp;dir=' . $columndir . '">' . $string[$column] . '</a>' . $columnicon;
}

list($extrasql, $params) = $filtering->get_sql_filter();
$usersbadges = get_usersbadges_listing(false, $sort, $dir, $page * $perpage, $perpage, $extrasql, $params);
$userbadgescount = $DB->count_records_sql('SELECT COUNT(DISTINCT userid) FROM {badge_issued}');
$userbadgessearchcount = get_usersbadges_listing(true, null, null, 0, 0, $extrasql, $params);

$strall = get_string('all');


if ($usersbadges) {

    raise_memory_limit(MEMORY_EXTRA);
    $finalusersbadges = array();
    foreach ($usersbadges as $userbadge) {

        if (!isset($finalusersbadges[$userbadge->userid])) {
            $userbadge->badges = array();
            $finalusersbadges[$userbadge->userid] = $userbadge;
        }

        $finalusersbadges[$userbadge->userid]->badges[$userbadge->id] = $userbadge;
    }

    // Only download data.
    if ($format) {

        $fields = array('userid' => 'userid', 'username' => 'username', 'firstname' => 'firstname', 'lastname' => 'lastname', 'email' => 'email');

        $data = array();
        $maxbadges = 1;

        foreach($finalusersbadges as $user) {

            $datarow = new stdClass();
            $datarow->userid    = $user->userid;
            $datarow->username  = $user->username;
            $datarow->firstname = $user->firstname;
            $datarow->lastname  = $user->lastname;
            $datarow->email     = $user->email;

            if (count($user->badges) > 0) {
                $k = 1;
                foreach($user->badges as $badge) {
                    $field = 'badge' . $k;
                    $datarow->$field = $badge->badgename;

                    $k++;
                }

                if (count($user->badges) > $maxbadges) {
                    $maxbadges = count($user->badges);
                }

            } else {
                // Not export users without badges.
                continue;
            }

            $data[] = $datarow;
        }

        for ($i = 1; $i <= $maxbadges; $i++) {
            $fieldname = 'badge' . $i;
            $fields[$fieldname] = $fieldname;
        }

        switch ($format) {
            case 'csv' : usersbadges_download_csv($fields, $data);
            case 'ods' : usersbadges_download_ods($fields, $data);
            case 'xls' : usersbadges_download_xls($fields, $data);

        }
        die;
    }
    // End download data.
}

echo $OUTPUT->header();

if ($extrasql !== '') {
    echo $OUTPUT->heading("$userbadgessearchcount / $userbadgescount ".get_string('users'));
    $usercount = $userbadgessearchcount;
} else {
    echo $OUTPUT->heading("$userbadgescount ".get_string('users'));
}

echo $OUTPUT->paging_bar($userbadgescount, $page, $perpage, $baseurl);

flush();


$table = null;

if (!$usersbadges) {
    $match = array();
    echo $OUTPUT->heading(get_string('notbadgesfound', 'report_usersbadges'));

    $table = NULL;

} else {

    $table = new html_table();
    $table->head = array ();
    $table->colclasses = array();
    $table->attributes['class'] = 'admintable generaltable';
    foreach ($columns as $field) {
        $table->head[] = ${$field};
    }
    $table->head[] = get_string('badges', 'badges');
    $table->colclasses[] = 'centeralign';
    $table->id = "usersbadges";

    foreach ($finalusersbadges as $user) {
        $badgecolumn = '';

        if (count($user->badges) > 0) {
            $badgecolumn = '<ul>';

            foreach ($user->badges as $badge) {
                $badgecolumn .= '<li>';
                $badgecolumn .=     '<a href="' . $CFG->wwwroot . '/badges/recipients.php?id=' . $badge->badgeid . '">' . $badge->badgename . '</a>';
                $badgecolumn .= '</li>';
            }

            $badgecolumn .= '</ul>';
        }

        $row = array ();
        $row[] = '<a href="' . $CFG->wwwroot . '/user/profile.php?id=' . $user->userid . '">' . $user->firstname . '</a>';
        $row[] = $user->lastname;
        $row[] = $user->email;
        $row[] = $badgecolumn;
        $table->data[] = $row;
    }

}


// Add filters.
$filtering->display_add();
$filtering->display_active();

if (!empty($table)) {
    echo html_writer::start_tag('div', array('class'=>'no-overflow'));
    echo html_writer::table($table);
    echo html_writer::end_tag('div');
    echo $OUTPUT->paging_bar($userbadgescount, $page, $perpage, $baseurl);

    // Download form.
    echo $OUTPUT->heading(get_string('download', 'admin'));

    echo $OUTPUT->box_start();
    echo '<form action="' . $baseurl . '">';
    echo '  <select name="format">';
    echo '    <option value="csv">' . get_string('downloadtext') . '</option>';
    echo '    <option value="ods">' . get_string('downloadods') . '</option>';
    echo '    <option value="xls">' . get_string('downloadexcel') . '</option>';
    echo '  </select>';
    echo '  <input type="submit" value="' . get_string('export', 'report_usersbadges') . '" />';
    echo '</form>';
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
