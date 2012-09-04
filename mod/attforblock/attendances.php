<?PHP // $Id: attendances.php,v 1.2.2.5 2009/02/23 19:22:40 dlnsk Exp $

//  Lists all the sessions for a course

    require_once('../../config.php');    
    require_once($CFG->libdir.'/blocklib.php');
    require_once($CFG->dirroot . '/mod/attforblock/locallib.php');
    require_once('lib.php');

    global $DB;

    $url = new moodle_url($CFG->wwwroot.$SCRIPT);
    $PAGE->set_url($url);

    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    $id 	= required_param('id', PARAM_INT);
    $sessionid  = required_param('sessionid', PARAM_INT);
    $group    	= optional_param('group', -1, PARAM_INT);              // Group to show
    $sort 	= optional_param('sort','lastname', PARAM_ALPHA);

    if (! $cm = $DB->get_record('course_modules', array('id'=>$id))) {
        error('Course Module ID was incorrect');
    }
    
    if (! $course = $DB->get_record('course', array('id'=>$cm->course))) {
        error('Course is misconfigured');
    }
    
    require_login($course->id);

    if (! $attforblock = $DB->get_record('attforblock', array('id'=> $cm->instance))) {
        error("Course module is incorrect");
    }
    if (! $user = $DB->get_record('user', array('id'=>$USER->id)) ) {
        error("No such user in this course");
    }
    
    if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
        print_error('badcontext');
    }
    
    $statlist = implode(',', array_keys( (array)get_statuses($course->id) ));
    if ($form = data_submitted()) {
    	$students = array();			// stores students ids
        $formarr = (array)$form;
        $i = 0;
        $now = time();
        foreach($formarr as $key => $value) {
                if(substr($key,0,7) == 'student' && $value !== '') {
                        $students[$i] = new Object();
                        $sid = substr($key,7);		// gets studeent id from radiobutton name
                        $students[$i]->studentid = $sid;
                        $students[$i]->statusid = $value;
                        $students[$i]->statusset = $statlist;
                        $students[$i]->remarks = array_key_exists('remarks'.$sid, $formarr) ? $formarr['remarks'.$sid] : '';
                        $students[$i]->sessionid = $sessionid;
                        $students[$i]->timetaken = $now;
                        $students[$i]->takenby = $USER->id;
                        $i++;
                }
        }
        $attforblockrecord = $DB->get_record('attforblock', array('course'=>$course->id));

        foreach($students as $student) {
            if ($log = $DB->get_record('attendance_log', array('sessionid'=>$sessionid, 'studentid'=>$student->studentid))) {
                $student->id = $log->id; // this is id of log
                $DB->update_record('attendance_log', $student);
            } else {
                $DB->insert_record('attendance_log', $student);
            }
        }
        $DB->set_field('attendance_sessions', 'lasttaken', $now, array('id'=>$sessionid));
        $DB->set_field('attendance_sessions', 'lasttakenby', $USER->id, array('id'=>$sessionid));

        attforblock_update_grades($attforblockrecord);
        add_to_log($course->id, 'attendance', 'updated', 'mod/attforblock/report.php?id='.$id, $user->lastname.' '.$user->firstname);
        $message = "";
        $message.= "<div style='text-align:center;height:30px;padding-top:150px;'>";
        $message.= get_string('attendancesuccess','attforblock');
        $message.= "</div>";
        redirect('manage.php?id='.$id, $message, 3);
    	exit();
    }
    
/// Print headers
    $navlinks[] = array('name' => $attforblock->name, 'link' => "view.php?id=$id", 'type' => 'activity');
    $navlinks[] = array('name' => get_string('update', 'attforblock'), 'link' => null, 'type' => 'activityinstance');
    $navigation = build_navigation($navlinks);
    print_header("$course->shortname: ".$attforblock->name.' - ' .get_string('update','attforblock'), $course->fullname,
                 $navigation, "", "", true, "&nbsp;", navmenu($course));

//check for hack
    if (!$sessdata = $DB->get_record('attendance_sessions', array('id'=>$sessionid))) {
		error("Required Information is missing", "manage.php?id=".$id);
    }
    //$help = $OUTPUT->help_icon('updateattendance', 'attforblock', '');
    $help = '';
    $update = $DB->count_records('attendance_log', array('sessionid'=>$sessionid));

    if ($update) {
        require_capability('mod/attforblock:changeattendances', $context);
        echo $OUTPUT->heading(get_string('update','attforblock').' ' .get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname.$help);
    } else {
        require_capability('mod/attforblock:takeattendances', $context);
        echo $OUTPUT->heading(get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname.$help);
    }

    /// find out current groups mode
    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm, true);

    $students = get_users_for_attendance($context, $sort,  $currentgroup);

    $sort = ($sort == 'firstname') ? 'firstname' : 'lastname';
    /// Now we need a menu for separategroups as well!
    if ($groupmode == VISIBLEGROUPS || 
            ($groupmode && has_capability('moodle/site:accessallgroups', $context))) {
        groups_print_activity_menu($cm, "attendances.php?id=$id&amp;sessionid=$sessionid&amp;sort=$sort");
    }
    $table = new html_table();
    $table->data[][] = '<b>'.get_string('sessiondate','attforblock').': '.userdate($sessdata->sessdate, get_string('strftimedate').', '.get_string('strftimehm', 'attforblock')).
                                                    ', "'.($sessdata->description ? $sessdata->description : get_string('nodescription', 'attforblock')).'"</b>';
    echo '<center>';
    echo html_writer::table($table);
    echo '</center>';

    $statuses = get_statuses($course->id);
    $i = 3;
    foreach($statuses as $st) {
            $tabhead[] = "<a href=\"javascript:select_all_in('TD', 'cell c{$i}', null);\"><u>$st->acronym</u></a>";
            $i++;
    }
    $tabhead[] = get_string('remarks','attforblock');

    $firstname = "<a href=\"attendances.php?id=$id&amp;sessionid=$sessionid&amp;sort=firstname\">".get_string('firstname').'</a>';
    $lastname  = "<a href=\"attendances.php?id=$id&amp;sessionid=$sessionid&amp;sort=lastname\">".get_string('lastname').'</a>';
    if ($CFG->fullnamedisplay == 'lastname firstname') { // for better view (dlnsk)
        $fullnamehead = "$lastname / $firstname";
    } else {
        $fullnamehead = "$firstname / $lastname";
    }
	
    if ($students) {
        unset($table);
        $table = new html_table();
        $table->width = '0%';
        $table->head[] = '#';
        $table->align[] = 'center';
        $table->size[] = '20px';
        
        $table->head[] = '';
        $table->align[] = '';
        $table->size[] = '1px';
        
        $table->head[] = $fullnamehead;
        $table->align[] = 'left';
        $table->size[] = '';
        $table->wrap[2] = 'nowrap';
        foreach ($tabhead as $hd) {
            $table->head[] = $hd;
            $table->align[] = 'center';
            $table->size[] = '20px';
        }
        $i = 0;
        foreach($students as $student) {
            $i++;
            $att = $DB->get_record('attendance_log', array('sessionid'=>$sessionid, 'studentid'=>$student->id));
            $table->data[$student->id][] = (!$att && $update) ? "<font color=\"red\"><b>$i</b></font>" : $i; 
            $table->data[$student->id][] = $OUTPUT->user_picture($student);
            $table->data[$student->id][] = "<a href=\"view.php?id=$id&amp;student={$student->id}\">".((!$att && $update) ? '<font color="red"><b>' : '').fullname($student).((!$att && $update) ? '</b></font>' : '').'</a>';

            foreach($statuses as $st) {
                 @$table->data[$student->id][] = '<input name="student'.$student->id.'" type="radio" value="'.$st->id.'" '.($st->id == $att->statusid ? 'checked' : '').'>';
            }
            $table->data[$student->id][] = '<input type="text" name="remarks'.$student->id.'" size="" value="'.($att ? $att->remarks : '').'">';
        }

        echo '<form name="takeattendance" method="post" action="attendances.php">';
        echo '<center>';
        echo html_writer::table($table);
        echo '<input type="hidden" name="id" value="'.$id.'">';
        echo '<input type="hidden" name="sessionid" value="'.$sessionid.'">';
        echo '<input type="hidden" name="formfrom" value="editsessvals">';
        echo '<input type="submit" name="esv" value="'.get_string('ok').'">';
        echo '</center>';
        echo '</form>';
    } else {
        echo $OUTPUT->heading(get_string('nothingtodisplay'));
    }
	 
    echo get_string('status','attforblock').':<br />';
    foreach($statuses as $st) {
            echo $st->acronym.' - '.$st->description.'<br />';
    }

    echo $OUTPUT->footer($course);
    
?>
