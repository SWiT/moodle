<?PHP 

    require_once('../../config.php'); 
    global $DB, $OUTPUT, $CFG;
    
    require_once($CFG->dirroot . '/mod/attforblock/locallib.php');
    require_once('lib.php');
    
    
    $url = new moodle_url($CFG->wwwroot.$SCRIPT);
    $PAGE->set_url($url);
    
    $id             = required_param('id', PARAM_INT);
    $submitsettings = optional_param('submitsettings', '', PARAM_ALPHANUM);
    $action         = optional_param('action', '', PARAM_MULTILANG);
    $stid           = optional_param('st', 0, PARAM_INT);
	
    if ($id) {
        if (! $cm = $DB->get_record('course_modules', array('id'=>$id))) {
            error('Course Module ID was incorrect');
        }
        if (! $course = $DB->get_record('course', array('id'=>$cm->course))) {
            error('Course is misconfigured');
        }
	    if (! $attforblock = $DB->get_record('attforblock', array('id'=>$cm->instance))) {
	        error("Course module is incorrect");
	    }
    }
    $attforblockrecord = $DB->get_record('attforblock', array('course'=>$course->id));


    require_login($course->id);

    if (! $user = $DB->get_record('user', array('id'=>$USER->id)) ) {
        error("No such user in this course");
    }
    
    if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
        print_error('badcontext');
    }
    
    require_capability('mod/attforblock:manageattendances', $context);
    
 /// Print headers
    $navlinks[] = array('name' => $attforblock->name, 'link' => "view.php?id=$id", 'type' => 'activity');
    $navlinks[] = array('name' => get_string('settings', 'attforblock'), 'link' => null, 'type' => 'activityinstance');
    $navigation = build_navigation($navlinks);
    print_header("$course->shortname: ".$attforblock->name.' - '.get_string('settings','attforblock'), $course->fullname,
                 $navigation, "", "", true, "&nbsp;", navmenu($course));
    
    if (!empty($action)) {
	    switch ($action) {
	    	case 'delete':
		    	if (!$rec = $DB->get_record('attendance_statuses', array('courseid'=>$course->id, 'id'=>$stid))) {
                            print_error('notfoundstatus', 'attforblock', "attsettings.php?id=$id");
                        }
		    	if ($DB->count_records('attendance_log', array('statusid'=>$stid))) {
                            print_error('cantdeletestatus', 'attforblock', "attsettings.php?id=$id");
		    	}
				
		    	$confirm = optional_param('confirm', 0, PARAM_INT);
		    	if (isset($confirm) && $confirm==1) {
		    		$DB->set_field('attendance_statuses', 'deleted', 1, array('id'=>$rec->id));
                                redirect('attsettings.php?id='.$id, get_string('statusdeleted','attforblock'), 3);
		    	}
                        echo $OUTPUT->heading(get_string('deletingstatus','attforblock').' :: ' .$course->fullname);
			
                        notice_yesno(get_string('deletecheckfull', '', get_string('variable', 'attforblock')).
					             '<br /><br />'.$rec->acronym.': '.
					             ($rec->description ? $rec->description : get_string('nodescription', 'attforblock')),
			                     "attsettings.php?id=$id&amp;st=$stid&amp;action=delete&amp;confirm=1", $_SERVER['HTTP_REFERER']);
                        exit;
	    	case 'show':
	    		$DB->set_field('attendance_statuses', 'visible', 1, array('id'=>$stid));
	    		break;
	    	case 'hide':
	    		$students = get_users_for_attendance($context);
                        list($studlist, $params) = $DB->get_in_or_equal(array_keys($students));
	    		if (! $DB->count_records_select('attendance_log', "studentid $studlist AND statusid = $stid", $params)) {
	    			$DB->set_field('attendance_statuses', 'visible', 0, array('id'=>$stid));
	    		} else {
	    			print_error('canthidestatus', 'attforblock', "attsettings.php?id=$id");
	    		}
	    		break;
	    	default: //Adding new status
				$newacronym			= optional_param('newacronym', '', PARAM_MULTILANG);
				$newdescription		= optional_param('newdescription', '', PARAM_MULTILANG);
				$newgrade			= optional_param('newgrade', 0, PARAM_INT);
	    		if (!empty($newacronym) && !empty($newdescription)) {
					unset($rec);
	    			$rec->courseid = $course->id;
					$rec->acronym = $newacronym;
					$rec->description = $newdescription;
					$rec->grade = $newgrade;
					$DB->insert_record('attendance_statuses', $rec);
					add_to_log($course->id, 'attendance', 'setting added', 'mod/attforblock/attsettings.php?course='.$course->id, $user->lastname.' '.$user->firstname);
	    		} else {
	    			print_error('cantaddstatus', 'attforblock', "attsettings.php?id=$id");
	    		}
	    		break;
	    }
    }
    
    show_tabs($cm, $context, 'settings');

    if ($submitsettings!='') {
            config_save(); //////////////////////////////
            notice(get_string('variablesupdated','attforblock'), 'attsettings.php?id='.$id);
    }

    $i = 1;
    $table = new html_table();
    $table->width = '100%';
    $table->head = array('#',
                         get_string('acronym','attforblock'),
                         get_string('description'),
                         get_string('grade'),
                         get_string('action'));
    $table->align = array('center', 'center', 'center', 'center', 'center', 'center');
	
    $statuses = get_statuses($course->id, false);
    $deltitle = get_string('delete');
    foreach($statuses as $st)
    {
            $table->data[$i][] = $i;
//		$table->data[$i][] = $st->status;
            $table->data[$i][] = '<input type="text" name="acronym['.$st->id.']" size="2" maxlength="2" value="'.$st->acronym.'" />';
            $table->data[$i][] = '<input type="text" name="description['.$st->id.']" size="30" maxlength="30" value="'.$st->description.'" />';
            $table->data[$i][] = '<input type="text" name="grade['.$st->id.']" size="4" maxlength="4" value="'.$st->grade.'" />';

            $action = $st->visible ? 'hide' : 'show';
            $titlevis = get_string($action);
            $deleteact = '';
            if (!$DB->count_records('attendance_log', array('statusid'=>$st->id))) {
                    $deleteact = "<a title=\"$deltitle\" href=\"attsettings.php?id=$cm->id&amp;st={$st->id}&amp;action=delete\">".
                                             "<img src=\"".$OUTPUT->pix_url('t/delete')."\" alt=\"$deltitle\" /></a>&nbsp;";
            }
            $table->data[$i][] = "<a title=\"$titlevis\" href=\"attsettings.php?id=$cm->id&amp;st={$st->id}&amp;action=$action\">".
                                                     "<img src=\"".$OUTPUT->pix_url("t/{$action}")."\" alt=\"$titlevis\" /></a>&nbsp;".
                                                     $deleteact;
            $i++;
    }
    $new_row = array('*',
                     '<input type="text" name="newacronym" size="2" maxlength="2" value="" />',
                     '<input type="text" name="newdescription" size="30" maxlength="30" value="" />',
                     '<input type="text" name="newgrade" size="4" maxlength="4" value="" />',
                     '<input type="submit" name="action" value="'.get_string('add', 'attforblock').'">'
                    );
    $table->data[$i] = $new_row;
	
    echo '<div align="center"><div class="generalbox boxwidthwide">';
    echo '<form aname="gsess" method="post" action="attsettings.php" onSubmit="return validateSession()">';
    echo '<h1 class="main help">'.get_string('myvariables','attforblock').$OUTPUT->help_icon('myvariables', 'attforblock', '').'</h1>';

    echo html_writer::table($table);

    echo '<input type="hidden" name="id" value="'.$id.'"><br />';
    echo '<input type="submit" name="submitsettings" value="'.get_string("update",'attforblock').'">';
    echo '</form></div></div>';

    echo $OUTPUT->footer($course);
	
	
function config_save()
{
    global $course, $user, $attforblockrecord, $DB;

    $acronym        = required_param_array('acronym', PARAM_ALPHANUM);
    $description    = required_param_array('description', PARAM_TEXT);
    $grade          = required_param_array('grade', PARAM_INT);

    foreach ($acronym as $id => $v) {
        $rec = $DB->get_record('attendance_statuses', array('id'=>$id));
        $rec->acronym = $acronym[$id];
        $rec->description = $description[$id];
        $rec->grade = $grade[$id];
        $DB->update_record('attendance_statuses', $rec);
        add_to_log($course->id, 'attendance', 'settings updated', 'mod/attforblock/attsettings.php?course='.$course->id, $user->lastname.' '.$user->firstname);
    }
    attforblock_update_grades($attforblockrecord);
}
	
?>	
