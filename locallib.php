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
 * This file contains the Courses status library functions.
 *
 * @package    report_usersbadges
 * @copyright 2017 David Herney Bernal - cirano
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function usersbadges_download_ods($fields, $data) {
    global $CFG, $SESSION, $DB;

    require_once $CFG->libdir . '/odslib.class.php';

    $filename = clean_filename(get_string('filename', 'report_usersbadges').'.ods');

    $workbook = new MoodleODSWorkbook('-');
    $workbook->send($filename);

    $worksheet = array();

    $worksheet[0] = $workbook->add_worksheet('');
    $col = 0;
    foreach ($fields as $fieldname) {
        $worksheet[0]->write(0, $col, $fieldname);
        $col++;
    }

    $row = 1;
    foreach ($data as $datarow) {
        $col = 0;
        foreach ($fields as $field=>$unused) {
            if (property_exists($datarow, $field)) {
                $worksheet[0]->write($row, $col, $datarow->$field);
            } else {
                $worksheet[0]->write($row, $col, '');
            }
            $col++;
        }
        $row++;
    }

    $workbook->close();
    die;
}

function usersbadges_download_xls($fields, $data) {
    global $CFG, $SESSION, $DB;

    require_once $CFG->libdir . '/excellib.class.php';

    $filename = clean_filename(get_string('filename', 'report_usersbadges').'.xls');

    $workbook = new MoodleExcelWorkbook('-');
    $workbook->send($filename);

    $worksheet = array();

    $worksheet[0] = $workbook->add_worksheet('');
    $col = 0;
    foreach ($fields as $fieldname) {
        $worksheet[0]->write(0, $col, $fieldname);
        $col++;
    }

    $row = 1;
    foreach ($data as $datarow) {
        $col = 0;
        foreach ($fields as $field=>$unused) {
            if (property_exists($datarow, $field)) {
                $worksheet[0]->write($row, $col, $datarow->$field);
            } else {
                $worksheet[0]->write($row, $col, '');
            }
            $col++;
        }
        $row++;
    }

    $workbook->close();
    die;
}

function usersbadges_download_csv($fields, $data) {
    global $CFG, $SESSION, $DB;

    require_once $CFG->libdir . '/csvlib.class.php';

    $filename = clean_filename(get_string('filename', 'report_usersbadges'));

    $csvexport = new csv_export_writer();
    $csvexport->set_filename($filename);
    $csvexport->add_data($fields);

    foreach ($data as $datarow) {
        $row = array();

        $onerow = array();
        foreach ($fields as $field=>$unused) {
            if (property_exists($datarow, $field)) {
                $onerow[] = $datarow->$field;
            } else {
                $onerow[] = '';
            }
        }
        $csvexport->add_data($onerow);
    }

    $csvexport->download_file();
    die;
}
