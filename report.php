<?php

// This file is part of the Certificate module for Moodle - http://moodle.org/
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
 * Handles viewing the report
 *
 * @package    mod_certificate
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('locallib.php');

$id   = required_param('id', PARAM_INT); // Course module ID
$sort = optional_param('sort', '', PARAM_RAW);
$download = optional_param('download', '', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);
$start = optional_param('start', null, PARAM_TEXT);
$end = optional_param('end', null, PARAM_TEXT);

$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', CERT_PER_PAGE, PARAM_INT);
// Ensure the perpage variable does not exceed the max allowed if
// the user has not specified they wish to view all certificates.
if (CERT_PER_PAGE !== 0) {
    if (($perpage > CERT_MAX_PER_PAGE) || ($perpage <= 0)) {
        $perpage = CERT_MAX_PER_PAGE;
    }
} else {
    $perpage = '9999999';
}

$url = new moodle_url('/mod/certificate/report.php', array('id'=>$id, 'page' => $page, 'perpage' => $perpage));
if ($download) {
    $url->param('download', $download);
}
if ($action) {
    $url->param('action', $action);
}

$PAGE->set_url($url);
$PAGE->requires->css('/mod/certificate/styles.css');

if (!$cm = get_coursemodule_from_id('certificate', $id)) {
    print_error('Course Module ID was incorrect');
}

if (!$course = $DB->get_record('course', array('id'=> $cm->course))) {
    print_error('Course is misconfigured');
}

if (!$certificate = $DB->get_record('certificate', array('id'=> $cm->instance))) {
    print_error('Certificate ID was incorrect');
}

// Requires a course login
require_login($course, false, $cm);

// Check capabilities
$context = context_module::instance($cm->id);
require_capability('mod/certificate:manage', $context);

// Declare some variables
$strcertificates = get_string('modulenameplural', 'certificate');
$strcertificate  = get_string('modulename', 'certificate');
$strto = get_string('awardedto_cert', 'certificate');
$strdate = get_string('receiveddate_cert', 'certificate');
$strgrade = get_string('grade','certificate');
$strcode = get_string('code', 'certificate');
$strreport= get_string('report', 'certificate');
$strdatesreport = get_string('report_date', 'certificate');
$strapply = get_string('apply', 'certificate');
$strcancel = get_string('cancel', 'certificate');

$PAGE->requires->js_call_amd('mod_certificate/navigation', 'init');

if (!$download) {
    $PAGE->navbar->add($strreport);
    $PAGE->set_title(format_string($certificate->name).": $strreport");
    $PAGE->set_heading($course->fullname);
    // Check to see if groups are being used in this choice
    if ($groupmode = groups_get_activity_groupmode($cm)) {
        groups_get_activity_group($cm, true);
    }
} else {
    $groupmode = groups_get_activity_groupmode($cm);
    // Get all results when $page and $perpage are 0
    $page = $perpage = 0;
}

// Ensure there are issues to display, if not display notice
if (!$users = certificate_get_issues($certificate->id, $DB->sql_fullname(), $groupmode, $cm, $page, $perpage, $start, $end)) {
    echo $OUTPUT->header();
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/certificate/report.php?id='.$id);
    //If filter not results
    echo '<form method="get" id="filter-dates" action="">';
    echo '<div>';
    echo '<span id="date-issued">'.$strdatesreport.'</span>';
    echo '<input id="date-start" value="'.$start.'" name="start" type="date">';
    echo '<input id="date-end" value="'.$end.'" name="end" type="date">';
    echo '<button class="btn btn-default" id="apply-filter" value="'.$id.'" name="id" type="submit">'.$strapply.'</button>';
    echo '<button class="btn btn-default" id="clear-filter" value="'.$id.'" name="id" type="submit">'.$strcancel.'</button>';
    echo '</div>';
    echo '</form>';
    echo $OUTPUT->notification(get_string('nocertificatesissued', 'certificate'));
    echo $OUTPUT->footer($course);
    exit();
}

if ($download == "ods") {
    require_once("$CFG->libdir/odslib.class.php");

    // Calculate file name
    $filename = certificate_get_certificate_filename($certificate, $cm, $course) . '.ods';
    // Creating a workbook
    $workbook = new MoodleODSWorkbook("-");
    // Send HTTP headers
    $workbook->send($filename);
    // Creating the first worksheet
    $myxls = $workbook->add_worksheet($strreport);

    // Print names of all the fields
    $myxls->write_string(0, 0, get_string("lastname"));
    $myxls->write_string(0, 1, get_string("firstname"));
    $myxls->write_string(0, 2, get_string("idnumber"));
    $myxls->write_string(0, 3, get_string("group"));
    $myxls->write_string(0, 4, $strdate);
    $myxls->write_string(0, 5, $strgrade);
    $myxls->write_string(0, 6, $strcode);

    // Generate the data for the body of the spreadsheet
    $i = 0;
    $row = 1;
    if ($users) {
        foreach ($users as $user) {
            $myxls->write_string($row, 0, $user->lastname);
            $myxls->write_string($row, 1, $user->firstname);
            $studentid = (!empty($user->idnumber)) ? $user->idnumber : " ";
            $myxls->write_string($row, 2, $studentid);
            $ug2 = '';
            if ($usergrps = groups_get_all_groups($course->id, $user->id)) {
                foreach ($usergrps as $ug) {
                    $ug2 = $ug2. $ug->name;
                }
            }
            $myxls->write_string($row, 3, $ug2);
            $myxls->write_string($row, 4, userdate($user->timecreated));
            $myxls->write_string($row, 5, certificate_get_grade($certificate, $course, $user->id));
            $myxls->write_string($row, 6, $user->code);
            $row++;
        }
        $pos = 6;
    }
    // Close the workbook
    $workbook->close();
    exit;
}

if ($download == "xls") {
    require_once("$CFG->libdir/excellib.class.php");

    // Calculate file name
    $filename = certificate_get_certificate_filename($certificate, $cm, $course) . '.xls';
    // Creating a workbook
    $workbook = new MoodleExcelWorkbook("-");
    // Send HTTP headers
    $workbook->send($filename);
    // Creating the first worksheet
    $myxls = $workbook->add_worksheet($strreport);

    // Print names of all the fields
    $myxls->write_string(0, 0, get_string("lastname"));
    $myxls->write_string(0, 1, get_string("firstname"));
    $myxls->write_string(0, 2, get_string("idnumber"));
    $myxls->write_string(0, 3, get_string("group"));
    $myxls->write_string(0, 4, $strdate);
    $myxls->write_string(0, 5, $strgrade);
    $myxls->write_string(0, 6, $strcode);

    // Generate the data for the body of the spreadsheet
    $i = 0;
    $row = 1;
    if ($users) {
        foreach ($users as $user) {
            $myxls->write_string($row, 0, $user->lastname);
            $myxls->write_string($row, 1, $user->firstname);
            $studentid = (!empty($user->idnumber)) ? $user->idnumber : " ";
            $myxls->write_string($row,2,$studentid);
            $ug2 = '';
            if ($usergrps = groups_get_all_groups($course->id, $user->id)) {
                foreach ($usergrps as $ug) {
                    $ug2 = $ug2 . $ug->name;
                }
            }
            $myxls->write_string($row, 3, $ug2);
            $myxls->write_string($row, 4, userdate($user->timecreated));
            $myxls->write_string($row, 5, certificate_get_grade($certificate, $course, $user->id));
            $myxls->write_string($row, 6, $user->code);
            $row++;
        }
        $pos = 6;
    }
    // Close the workbook
    $workbook->close();
    exit;
}

if ($download == "txt") {
    $filename = certificate_get_certificate_filename($certificate, $cm, $course) . '.txt';

    header("Content-Type: application/download\n");
    // Totara: Send the content-disposition header with properly encoded filename.
    require_once($CFG->libdir.'/filelib.php');
    header(make_content_disposition('attachment', $filename));
    header("Expires: 0");
    header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
    header("Pragma: public");

    // Print names of all the fields
    echo get_string("lastname"). "\t" .get_string("firstname") . "\t". get_string("idnumber") . "\t";
    echo get_string("group"). "\t";
    echo $strdate. "\t";
    echo $strgrade. "\t";
    echo $strcode. "\n";

    // Generate the data for the body of the spreadsheet
    $i=0;
    $row=1;
    if ($users) foreach ($users as $user) {
        echo $user->lastname;
        echo "\t" . $user->firstname;
        $studentid = " ";
        if (!empty($user->idnumber)) {
            $studentid = $user->idnumber;
        }
        echo "\t" . $studentid . "\t";
        $ug2 = '';
        if ($usergrps = groups_get_all_groups($course->id, $user->id)) {
            foreach ($usergrps as $ug) {
                $ug2 = $ug2. $ug->name;
            }
        }
        echo $ug2 . "\t";
        echo userdate($user->timecreated) . "\t";
        echo certificate_get_grade($certificate, $course, $user->id) . "\t";
        echo $user->code . "\n";
        $row++;
    }
    exit;
}

#@Solutto
#Download certificates in zip
if ($download == "zip") {
    $fs = get_file_storage();
    $zip = new ZipArchive();
    
    $tmpfile = tempnam('.', '');
    $zip->open($tmpfile, ZipArchive::CREATE);
    $tempdirname  = "";
    $certificates = [];
    foreach($users as $user) {
        // Make temp dir to save the pdf for each user
        $dirname = "UserID_".$user->id."_certificates_can_delete";
        make_temp_directory($dirname); 
        $tempdirname = "$CFG->tempdir/".$dirname;
        //Get the issues
        $certrecord = $DB->get_record('certificate_issues', array('userid' => $user->id, 'certificateid' => $certificate->id));
        $component = 'mod_certificate';
        $filearea = 'issue';
        $files = $fs->get_area_files($context->id, $component, $filearea, $certrecord->id);
        # Modify the certificate name in the zip
        $course     = get_course($certificate->course);
        $custom_filename = custom_certificate_filename($user, $course, $certificate);
        $custom_filename .= '.pdf';
        foreach ($files as $file) {
            if($file->get_filesize() == 0) continue;
            $certificates[] = $custom_filename;
            $filename = $custom_filename;
            $contents = $file->get_content();
            file_put_contents($tempdirname."/".$filename, $contents);
            $zip->addFile($tempdirname."/".$filename, $filename);
        }
    }
    $zip->close();
    
    foreach ($certificates as $filename) {
        unlink($tempdirname."/".$filename);
    }
    rmdir($tempdirname);
    if($start != null && $end != null){
        $date_ini = date("dmY", strtotime($start));
        $date_fin = date("dmY", strtotime($end));
        $date = $date_ini.'_'.$date_fin;
    }else{
        $date = strftime("%d%m%Y" , time());
    }
    
    $zipfilename = $date.'_'.$certificate->name.'.zip';
    $zipfilename = clean_filename($zipfilename);
    header("Content-Disposition: attachment; filename=\"" . basename($zipfilename) . "\"");
    header('Content-type: application/zip');
    readfile($tmpfile);
    unlink($tmpfile);
    exit;
}

$usercount = count(certificate_get_issues($certificate->id, $DB->sql_fullname(), $groupmode, $cm, '', '', $start, $end));

// Create the table for the users
$table = new html_table();
$table->head  = array($strto, $strdate, $strgrade, $strcode);
$table->align = array("left", "left", "center", "center");
foreach ($users as $user) {
    $name = $OUTPUT->user_picture($user) . fullname($user);
    $date = userdate($user->timecreated) . certificate_print_user_files($certificate, $user->id, $context->id);
    $code = $user->code;
    $table->data[] = array ($name, $date, certificate_get_grade($certificate, $course, $user->id), $code);
}

// Create table to store buttons
$tablebutton = new html_table();
$tablebutton->attributes['class'] = 'downloadreport';
$btndownloadods = $OUTPUT->single_button(new moodle_url("report.php", array('id'=>$cm->id, 'download'=>'ods', 'start' => $start, 'end' => $end)), get_string("downloadods"));
$btndownloadxls = $OUTPUT->single_button(new moodle_url("report.php", array('id'=>$cm->id, 'download'=>'xls', 'start' => $start, 'end' => $end)), get_string("downloadexcel"));
$btndownloadtxt = $OUTPUT->single_button(new moodle_url("report.php", array('id'=>$cm->id, 'download'=>'txt', 'start' => $start, 'end' => $end)), get_string("downloadtext"));
#@Solutto
#Add the button to download all in zip
$btndownloadzip = $OUTPUT->single_button(new moodle_url("report.php", array('id'=>$cm->id, 'download'=>'zip','start' => $start, 'end' => $end)), get_string("downloadall"));
$tablebutton->data[] = array($btndownloadods, $btndownloadxls, $btndownloadtxt, $btndownloadzip);

echo $OUTPUT->header();
groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/certificate/report.php?id='.$id);

//Add datepicker filter
echo '<form method="get" id="filter-dates" action="">';
echo '<div>';
echo '<span id="date-issued">'.$strdatesreport.'</span>';
echo '<input id="date-start" value="'.$start.'" name="start" type="date">';
echo '<input id="date-end" value="'.$end.'" name="end" type="date">';
echo '<button class="btn btn-default" id="apply-filter" value="'.$id.'" name="id" type="submit">'.$strapply.'</button>';
echo '<button class="btn btn-default" id="clear-filter" value="'.$id.'" name="id" type="submit">'.$strcancel.'</button>';
echo '</div>';
echo '</form>';
echo $OUTPUT->heading(get_string('modulenameplural', 'certificate'));
echo $OUTPUT->paging_bar($usercount, $page, $perpage, $url);
echo '<br />';
echo html_writer::table($table);
echo html_writer::tag('div', html_writer::table($tablebutton), array('style' => 'margin:auto; width:50%'));
echo $OUTPUT->footer($course);
