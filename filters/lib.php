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
 * This file contains the userbadges filter API.
 *
 * @package    report_usersbadges
 * @copyright 2018 David Herney Bernal - cirano
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once $CFG->dirroot . '/user/filters/lib.php';

/**
 * Userbadges filtering wrapper class.
 *
 * @copyright 2018 David Herney Bernal - cirano
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_usersbadges_filtering {
    /** @var array */
    public $_fields;
    /** @var \userbadges_add_filter_form */
    public $_addform;
    /** @var \userbadges_active_filter_form */
    public $_activeform;

    /**
     * Contructor
     * @param array $fieldnames array of visible user fields
     * @param string $baseurl base url used for submission/return, null if the same of current page
     * @param array $extraparams extra page parameters
     */
    public function __construct($fieldnames = null, $baseurl = null, $extraparams = null) {
        global $SESSION;

        if (!isset($SESSION->userbadges_filtering)) {
            $SESSION->userbadges_filtering = array();
        }

        if (empty($fieldnames)) {
            $fieldnames = array('badges' => 0, 'lastname' => 1, 'firstname' => 1, 'username' => 1, 'email' => 1);
        }

        $this->_fields  = array();

        foreach ($fieldnames as $fieldname => $advanced) {
            if ($field = $this->get_field($fieldname, $advanced)) {
                $this->_fields[$fieldname] = $field;
            }
        }

        // Fist the new filter form.
        $this->_addform = new userbadges_add_filter_form($baseurl, array('fields' => $this->_fields, 'extraparams' => $extraparams));
        if ($adddata = $this->_addform->get_data()) {
            foreach ($this->_fields as $fname => $field) {
                $data = $field->check_data($adddata);
                if ($data === false) {
                    continue; // Nothing new.
                }
                if (!array_key_exists($fname, $SESSION->userbadges_filtering)) {
                    $SESSION->userbadges_filtering[$fname] = array();
                }
                $SESSION->userbadges_filtering[$fname][] = $data;
            }
            // Clear the form.
            $_POST = array();
            $this->_addform = new userbadges_add_filter_form($baseurl, array('fields' => $this->_fields, 'extraparams' => $extraparams));
        }

        // Now the active filters.
        $this->_activeform = new userbadges_active_filter_form($baseurl, array('fields' => $this->_fields, 'extraparams' => $extraparams));
        if ($adddata = $this->_activeform->get_data()) {
            if (!empty($adddata->removeall)) {
                $SESSION->userbadges_filtering = array();

            } else if (!empty($adddata->removeselected) and !empty($adddata->filter)) {
                foreach ($adddata->filter as $fname => $instances) {
                    foreach ($instances as $i => $val) {
                        if (empty($val)) {
                            continue;
                        }
                        unset($SESSION->userbadges_filtering[$fname][$i]);
                    }
                    if (empty($SESSION->userbadges_filtering[$fname])) {
                        unset($SESSION->userbadges_filtering[$fname]);
                    }
                }
            }
            // Clear+reload the form.
            $_POST = array();
            $this->_activeform = new userbadges_active_filter_form($baseurl, array('fields' => $this->_fields, 'extraparams' => $extraparams));
        }
        // Now the active filters.
    }

    /**
     * Creates known user filter if present
     * @param string $fieldname
     * @param boolean $advanced
     * @return object filter
     */
    public function get_field($fieldname, $advanced) {
        global $USER, $CFG, $DB, $SITE;

        switch ($fieldname) {
            case 'username':    return new user_filter_text('username', get_string('username'), $advanced, 'username');
            case 'lastname':    return new user_filter_text('lastname', get_string('lastname'), $advanced, 'lastname');
            case 'firstname':   return new user_filter_text('firstname', get_string('firstname'), $advanced, 'firstname');
            case 'email':       return new user_filter_text('email', get_string('email'), $advanced, 'email');
            case 'badges':      return new user_filter_text('badges', get_string('badges', 'badges'), $advanced, 'b.name');

            default:
                return null;
        }
    }

    /**
     * Returns sql where statement based on active user filters
     * @param string $extra sql
     * @param array $params named params (recommended prefix ex)
     * @return array sql string and $params
     */
    public function get_sql_filter($extra='', array $params=null) {
        global $SESSION;

        $sqls = array();
        if ($extra != '') {
            $sqls[] = $extra;
        }
        $params = (array)$params;

        if (!empty($SESSION->userbadges_filtering)) {
            foreach ($SESSION->userbadges_filtering as $fname => $datas) {
                if (!array_key_exists($fname, $this->_fields)) {
                    continue; // Filter not used.
                }
                $field = $this->_fields[$fname];
                foreach ($datas as $i => $data) {
                    list($s, $p) = $field->get_sql_filter($data);
                    $sqls[] = $s;
                    $params = $params + $p;
                }
            }
        }

        if (empty($sqls)) {
            return array('', array());
        } else {
            $sqls = implode(' AND ', $sqls);
            return array($sqls, $params);
        }
    }

    /**
     * Print the add filter form.
     */
    public function display_add() {
        $this->_addform->display();
    }

    /**
     * Print the active filter form.
     */
    public function display_active() {
        $this->_activeform->display();
    }

}

/**
 * The base userbadges filter class. All abstract classes must be implemented.
 *
 * @copyright 2018 David Herney Bernal - cirano
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_userbadges_filter_type {
    /**
     * The name of this filter instance.
     * @var string
     */
    public $_name;

    /**
     * The label of this filter instance.
     * @var string
     */
    public $_label;

    /**
     * Advanced form element flag
     * @var bool
     */
    public $_advanced;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     */
    public function __construct($name, $label, $advanced) {
        $this->_name     = $name;
        $this->_label    = $label;
        $this->_advanced = $advanced;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    public function get_sql_filter($data) {
        print_error('mustbeoveride', 'debug', '', 'get_sql_filter');
    }

    /**
     * Retrieves data from the form data
     * @param stdClass $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    public function check_data($formdata) {
        print_error('mustbeoveride', 'debug', '', 'check_data');
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param moodleform $mform a MoodleForm object to setup
     */
    public function setupForm(&$mform) {
        print_error('mustbeoveride', 'debug', '', 'setupForm');
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label($data) {
        print_error('mustbeoveride', 'debug', '', 'get_label');
    }
}

/**
 * Return filtered (if provided) list of usersbadges.
 *
 * @param bool $count If return counter or records
 * @param string $sort An SQL field to sort by
 * @param string $dir The sort direction ASC|DESC
 * @param int $page The page or records to return
 * @param int $recordsperpage The number of records to return per page
 * @param string $extraselect An additional SQL select statement to append to the query
 * @param array $extraparams Additional parameters to use for the above $extraselect
 * @return array Array of records
 */
function get_usersbadges_listing($count = false, $sort = 'u.firstname', $dir = 'ASC', $page = 0, $recordsperpage = 0,
                           $extraselect = '', array $extraparams = null) {
    global $DB, $CFG;

    $select = "";
    $params = array();

    if ($extraselect) {
        $select = 'WHERE ' . $extraselect;
        $params = (array)$extraparams;
    }

    if ($sort) {
        $sort = " ORDER BY $sort $dir";
    }

    if ($count) {
        return $DB->count_records_sql("SELECT COUNT(distinct bi.userid)
                                   FROM {badge_issued} bi
                                   INNER JOIN {user} u ON bi.userid = u.id
                                   INNER JOIN {badge} b ON bi.badgeid = b.id
                                  $select", $params);
    } else {
        return $DB->get_records_sql("SELECT bi.id, u.id userid, u.username, u.email, u.firstname, u.lastname, b.name badgename, b.id badgeid
                                   FROM {badge_issued} bi
                                   INNER JOIN {user} u ON bi.userid = u.id
                                   INNER JOIN {badge} b ON bi.badgeid = b.id
                                  $select $sort", $params, $page, $recordsperpage);
    }

}