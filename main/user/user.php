<?php
/* For licensing terms, see /license.txt */

/**
 *	This script displays a list of the users of the current course.
 *	Course admins can change user permissions, subscribe and unsubscribe users...
 *
 *	- show users registered in courses;
 *
 *	@author Roan Embrechts
 *	@author Julio Montoya Armas, Several fixes
 *	@package chamilo.user
 */
$use_anonymous = true;
require_once '../inc/global.inc.php';
$current_course_tool  = TOOL_USER;
$this_section = SECTION_COURSES;

// notice for unauthorized people.
api_protect_course_script(true);

if (!api_is_platform_admin(true)) {
    if (!api_is_course_admin() && !api_is_coach()) {
        if (api_get_course_setting('allow_user_view_user_list') == 0) {
            api_not_allowed(true);
        }
    }
}

/* Constants and variables */
$course_code = Database::escape_string(api_get_course_id());
$sessionId = api_get_session_id();
$is_western_name_order = api_is_western_name_order();
$sort_by_first_name = api_sort_by_first_name();
$course_info = api_get_course_info();
$user_id = api_get_user_id();
$_user = api_get_user_info();
$courseCode = $course_info['code'];
$courseId = $course_info['real_id'];
$type = isset($_REQUEST['type']) ? intval($_REQUEST['type']) : STUDENT;

//Can't auto unregister from a session
if (!empty($sessionId)) {
    $course_info['unsubscribe']  = 0;
}

/* Unregistering a user section	*/
if (api_is_allowed_to_edit(null, true)) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'unsubscribe':
                // Make sure we don't unsubscribe current user from the course
                if (is_array($_POST['user'])) {
                    $user_ids = array_diff($_POST['user'], array($user_id));
                    if (count($user_ids) > 0) {
                        CourseManager::unsubscribe_user($user_ids, $courseCode);
                        Display::addFlash(
                            Display::return_message(get_lang('UsersUnsubscribed'))
                        );
                    }
                }
        }
    }
}

// Getting extra fields that have the filter option "on"
$extraField = new ExtraField('user');
$extraFields = $extraField->get_all(array('filter = ?' => 1));
$user_image_pdf_size = 80;

if (api_is_allowed_to_edit(null, true)) {
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'set_tutor':
                $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
                $isTutor = isset($_GET['is_tutor']) ? intval($_GET['is_tutor']) : 0;
                $userInfo = api_get_user_info($userId);
                
                if (!empty($userId)) {
                    if (!$sessionId) {
                        if ($userInfo['status'] != INVITEE) {
                            CourseManager::updateUserCourseTutor(
                                $userId,
                                $courseId,
                                $isTutor
                            );
                        } else {
                            Display::addFlash(Display::return_message(get_lang('InviteesCantBeTutors'), 'error'));
                        }
                    }
                }
                break;
            case 'export':
                $table_course_user = Database::get_main_table(TABLE_MAIN_COURSE_USER);
                $table_users = Database::get_main_table(TABLE_MAIN_USER);
                $is_western_name_order = api_is_western_name_order();

                $data = array();
                $a_users = array();
                $current_access_url_id = api_get_current_access_url_id();
                $extra_fields = UserManager::get_extra_user_data(
                    api_get_user_id(),
                    false,
                    false,
                    false,
                    true
                );

                $extra_fields = array_keys($extra_fields);
                $select_email_condition = '';

                if (api_get_setting('show_email_addresses') == 'true') {
                    $select_email_condition = ' user.email, ';
                    if ($sort_by_first_name) {
                        $a_users[0] = array(
                            'id',
                            get_lang('FirstName'),
                            get_lang('LastName'),
                            get_lang('Username'),
                            get_lang('Email'),
                            get_lang('Phone'),
                            get_lang('OfficialCode'),
                            get_lang('Active')
                        );
                    } else {
                        $a_users[0] = array(
                            'id',
                            get_lang('LastName'),
                            get_lang('FirstName'),
                            get_lang('Username'),
                            get_lang('Email'),
                            get_lang('Phone'),
                            get_lang('OfficialCode'),
                            get_lang('Active')
                        );
                    }
                } else {
                    if ($sort_by_first_name) {
                        $a_users[0] = array(
                            'id',
                            get_lang('FirstName'),
                            get_lang('LastName'),
                            get_lang('Username'),
                            get_lang('Phone'),
                            get_lang('OfficialCode'),
                            get_lang('Active')
                        );
                    } else {
                        $a_users[0] = array(
                            'id',
                            get_lang('LastName'),
                            get_lang('FirstName'),
                            get_lang('Username'),
                            get_lang('Phone'),
                            get_lang('OfficialCode'),
                            get_lang('Active')
                        );
                    }
                }

                $legal = '';

                if (isset($course_info['activate_legal']) AND $course_info['activate_legal'] == 1) {
                    $legal = ', legal_agreement';
                    $a_users[0][] = get_lang('LegalAgreementAccepted');
                }

                if ($_GET['format'] == 'pdf') {
                    $select_email_condition = ' user.email, ';
                    if ($is_western_name_order) {
                        $a_users[0] = array(
                            '#',
                            get_lang('UserPicture'),
                            get_lang('OfficialCode'),
                            get_lang('FirstName') . ', ' . get_lang('LastName'),
                            get_lang('Email'),
                            get_lang('Phone')
                        );
                    } else {
                        $a_users[0] = array(
                            '#',
                            get_lang('UserPicture'),
                            get_lang('OfficialCode'),
                            get_lang('LastName') . ', ' . get_lang('FirstName'),
                            get_lang('Email'),
                            get_lang('Phone')
                        );
                    }
                }

                $a_users[0] = array_merge($a_users[0], $extra_fields);

                // users subscribed to the course through a session.
                if (api_get_session_id()) {
                    $table_session_course_user = Database::get_main_table(TABLE_MAIN_SESSION_COURSE_USER);
                    $sql = "SELECT DISTINCT
                                user.user_id, ".($is_western_name_order ? "user.firstname, user.lastname" : "user.lastname, user.firstname").",
                                user.username,
                                $select_email_condition
                                phone,
                                user.official_code,
                                active
                                $legal
                            FROM $table_session_course_user as session_course_user,
                            $table_users as user ";
                    if (api_is_multiple_url_enabled()) {
                        $sql .= ' , '.Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_USER).' au ';
                    }
                    $sql .=" WHERE c_id = '$courseId' AND session_course_user.user_id = user.user_id ";
                    $sql .= ' AND session_id = '.$sessionId;

                    if (api_is_multiple_url_enabled()) {
                        $sql .= " AND user.user_id = au.user_id AND access_url_id =  $current_access_url_id  ";
                    }

                    // only users no coaches/teachers
                    if ($type == COURSEMANAGER) {
                        $sql .= " AND session_course_user.status = 2 ";
                    } else {
                        $sql .= " AND session_course_user.status = 0 ";
                    }
                    $sql .= $sort_by_first_name ? ' ORDER BY user.firstname, user.lastname' : ' ORDER BY user.lastname, user.firstname';

                    $rs = Database::query($sql);
                    $counter = 1;

                    while ($user = Database:: fetch_array($rs, 'ASSOC')) {
                        if (isset($user['legal_agreement'])) {
                            if ($user['legal_agreement'] == 1) {
                                $user['legal_agreement'] = get_lang('Yes');
                            } else {
                                $user['legal_agreement'] = get_lang('No');
                            }
                        }
                        $extra_fields = UserManager::get_extra_user_data(
                            $user['user_id'],
                            false,
                            false,
                            false,
                            true
                        );
                        if (!empty($extra_fields)) {
                            foreach($extra_fields as $key => $extra_value) {
                                $user[$key] = $extra_value;
                            }
                        }
                        $data[] = $user;
                        if ($_GET['format'] == 'pdf') {
                            $user_info = api_get_user_info($user['user_id']);
                            $user_image = '<img src="'.$user_info['avatar'].'" width ="'.$user_image_pdf_size.'px" />';

                            if ($is_western_name_order) {
                                $user_pdf = array(
                                    $counter,
                                    $user_image,
                                    $user['official_code'],
                                    $user['firstname'] . ', ' . $user['lastname'],
                                    $user['email'],
                                    $user['phone']
                                );
                            } else {
                                $user_pdf = array(
                                    $counter,
                                    $user_image,
                                    $user['official_code'],
                                    $user['lastname'] . ', ' . $user['firstname'],
                                    $user['email'],
                                    $user['phone']
                                );
                            }

                            $a_users[] = $user_pdf;
                        } else {
                            $a_users[] = $user;
                        }
                        $counter++;
                    }
                }

                if ($sessionId == 0) {
                    // users directly subscribed to the course
                    $table_course_user = Database :: get_main_table(TABLE_MAIN_COURSE_USER);
                    $sql = "SELECT DISTINCT
					            user.user_id, ".($is_western_name_order ? "user.firstname, user.lastname" : "user.lastname, user.firstname").",
					            user.username,
					            $select_email_condition
					            phone,
					            user.official_code,
					            active $legal
                            FROM $table_course_user as course_user, $table_users as user ";
                    if (api_is_multiple_url_enabled()) {
                        $sql .= ' , '.Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_USER).' au ';
                    }
                    $sql .= " WHERE
                            c_id = '$courseId' AND
                            course_user.relation_type<>".COURSE_RELATION_TYPE_RRHH." AND
                            course_user.user_id = user.user_id ";

                    if (api_is_multiple_url_enabled()) {
                        $sql .= " AND user.user_id = au.user_id  AND access_url_id =  $current_access_url_id  ";
                    }

                    // only users no teachers/coaches
                    if ($type == COURSEMANAGER) {
                        $sql .= " AND course_user.status = 1 ";
                    } else {
                        $sql .= " AND course_user.status = 5 ";
                    }

                    $sql .= ($sort_by_first_name ? " ORDER BY user.firstname, user.lastname" : " ORDER BY user.lastname, user.firstname");

                    $rs = Database::query($sql);
                    $counter = 1;
                    while ($user = Database::fetch_array($rs, 'ASSOC')) {
                        if (isset($user['legal_agreement'])) {
                            if ($user['legal_agreement'] == 1) {
                                $user['legal_agreement'] = get_lang('Yes');
                            } else {
                                $user['legal_agreement'] = get_lang('No');
                            }
                        }

                        $extra_fields = UserManager::get_extra_user_data(
                            $user['user_id'],
                            false,
                            false,
                            false,
                            true
                        );
                        if (!empty($extra_fields)) {
                            foreach ($extra_fields as $key => $extra_value) {
                                $user[$key] = $extra_value;
                            }
                        }
                        if ($_GET['format'] == 'pdf') {
                            $user_info = api_get_user_info($user['user_id']);

                            $user_image = '<img src="'.$user_info['avatar'].'" width ="'.$user_image_pdf_size.'px" />';

                            if ($is_western_name_order) {
                                $user_pdf = array(
                                    $counter,
                                    $user_image,
                                    $user['official_code'],
                                    $user['firstname'] . ', ' . $user['lastname'],
                                    $user['email'],
                                    $user['phone']
                                );
                            } else {
                                $user_pdf = array(
                                    $counter,
                                    $user_image,
                                    $user['official_code'],
                                    $user['lastname'] . ', ' . $user['firstname'],
                                    $user['email'],
                                    $user['phone']
                                );
                            }

                            $a_users[] = $user_pdf;
                        } else {
                            $a_users[] = $user;
                        }
                        $data[] = $user;
                        $counter++;
                    }
                }

                $fileName = get_lang('StudentList');
                $pdfTitle = get_lang('StudentList');

                if ($type == COURSEMANAGER) {
                    $fileName = get_lang('Teachers');
                    $pdfTitle = get_lang('Teachers');
                }

                switch ($_GET['format']) {
                    case 'csv' :
                        Export::arrayToCsv($a_users, $fileName);
                        exit;
                    case 'xls' :
                        Export::arrayToXls($a_users, $fileName);
                        exit;
                    case 'pdf' :
                        $header_attributes = array(
                            array('style' => 'width:10px'),
                            array('style' => 'width:30px'),
                            array('style' => 'width:50px'),
                            array('style' => 'width:500px'),
                        );
                        $params = array(
                            'add_signatures' => false,
                            'filename' => $fileName,
                            'pdf_title' => $pdfTitle,
                            'header_attributes' => $header_attributes
                        );

                        Export::export_table_pdf($a_users, $params);
                        exit;
                }
        }
    }
} // end if allowed to edit

if (api_is_allowed_to_edit(null, true)) {
    // Unregister user from course
    if (isset($_REQUEST['unregister']) && $_REQUEST['unregister']) {
        if (isset($_GET['user_id']) && is_numeric($_GET['user_id']) &&
            ($_GET['user_id'] != $_user['user_id'] || api_is_platform_admin())
        ) {
            $user_id = intval($_GET['user_id']);
            $tbl_user = Database::get_main_table(TABLE_MAIN_USER);
            $tbl_session_rel_course = Database::get_main_table(TABLE_MAIN_SESSION_COURSE);
            $tbl_session_rel_user = Database::get_main_table(TABLE_MAIN_SESSION_USER);

            $sql = 'SELECT user.user_id
					FROM '.$tbl_user.' user
					INNER JOIN '.$tbl_session_rel_user.' reluser
					ON user.user_id = reluser.user_id AND reluser.relation_type<>'.SESSION_RELATION_TYPE_RRHH.'
					INNER JOIN '.$tbl_session_rel_course.' rel_course
					ON rel_course.session_id = reluser.session_id
					WHERE
					    user.user_id = "'.$user_id.'" AND
					    rel_course.c_id = "'.$courseId.'"';

            $result = Database::query($sql);
            $row = Database::fetch_array($result, 'ASSOC');
            if ($row['user_id'] == $user_id || $row['user_id'] == "") {
                CourseManager::unsubscribe_user($_GET['user_id'], $courseCode);
                Display::addFlash(Display::return_message(get_lang('UserUnsubscribed')));
            } else {
                Display::addFlash(Display::return_message(get_lang('ThisStudentIsSubscribeThroughASession')));
            }
        }
    }
} else {
    // If student can unsubscribe
    if (isset($_REQUEST['unregister']) && $_REQUEST['unregister'] == 'yes') {
        if ($course_info['unsubscribe'] == 1) {
            $user_id = api_get_user_id();
            CourseManager::unsubscribe_user($user_id, $course_info['code']);
            header('Location: '.api_get_path(WEB_PATH).'user_portal.php');
            exit;
        }
    }
}

if (!$is_allowed_in_course) {
    api_not_allowed(true);
}

// Statistics
Event::event_access_tool(TOOL_USER);

/**
 * Get the users to display on the current page.
 */
function get_number_of_users()
{
    $counter = 0;
    $sessionId = api_get_session_id();
    $courseCode = api_get_course_id();
    $active = isset($_GET['active']) ? $_GET['active'] : null;

    if (!empty($sessionId)) {
        $a_course_users = CourseManager::get_user_list_from_course_code(
            $courseCode,
            $sessionId,
            null,
            null,
            null,
            null,
            false,
            false,
            null,
            null,
            null,
            $active
        );
    } else {
        $a_course_users = CourseManager::get_user_list_from_course_code(
            $courseCode,
            0,
            null,
            null,
            null,
            null,
            false,
            false,
            null,
            null,
            null,
            $active
        );
    }

    foreach ($a_course_users as $o_course_user) {
        if ((isset($_GET['keyword']) &&
                searchUserKeyword(
                    $o_course_user['firstname'],
                    $o_course_user['lastname'],
                    $o_course_user['username'],
                    $o_course_user['official_code'],
                    $_GET['keyword']
                )
            ) || !isset($_GET['keyword']) || empty($_GET['keyword'])
        ) {
            $counter++;
        }
    }
    return $counter;
}

/**
 * @param string $firstname
 * @param string $lastname
 * @param string $username
 * @param string $official_code
 * @param $keyword
 * @return bool
 */
function searchUserKeyword($firstname, $lastname, $username, $official_code, $keyword) {
    if (
        api_strripos($firstname, $keyword) !== false ||
        api_strripos($lastname, $keyword) !== false ||
        api_strripos($username, $keyword) !== false ||
        api_strripos($official_code, $keyword) !== false
    ) {
        return true;
    } else {
        return false;
    }
}

/**
 * Get the users to display on the current page.
 */
function get_user_data($from, $number_of_items, $column, $direction)
{
    global $is_western_name_order;
    global $extraFields;

    $type = isset($_REQUEST['type']) ? intval($_REQUEST['type']) : STUDENT;
    $course_info = api_get_course_info();
    $sessionId = api_get_session_id();
    $course_code = $course_info['code'];
    $a_users = array();
    $limit = null;

    // limit
    if (!isset($_GET['keyword']) || empty($_GET['keyword'])) {
        $limit = 'LIMIT '.intval($from).','.intval($number_of_items);
    }

    if (!in_array($direction, array('ASC', 'DESC'))) {
        $direction = 'ASC';
    }

    switch ($column) {
        case 2: //official code
            $order_by = 'ORDER BY user.official_code '.$direction;
            break;
        case 3:
            if ($is_western_name_order) {
                $order_by = 'ORDER BY user.firstname '.$direction.', user.lastname '.$direction;
            } else {
                $order_by = 'ORDER BY user.lastname '.$direction.', user.firstname '.$direction;
            }
            break;
        case 4:
            if ($is_western_name_order) {
                $order_by = 'ORDER BY user.lastname '.$direction.', user.firstname '.$direction;
            } else {
                $order_by = 'ORDER BY user.firstname '.$direction.', user.lastname '.$direction;
            }
            break;
        case 5: //username
            $order_by = 'ORDER BY user.username '.$direction;
            break;
        default:
            if ($is_western_name_order) {
                $order_by = 'ORDER BY user.lastname '.$direction.', user.firstname '.$direction;
            } else {
                $order_by = 'ORDER BY user.firstname '.$direction.', user.lastname '.$direction;
            }
            break;
    }

    $active = isset($_GET['active']) ? $_GET['active'] : null;

    if (empty($sessionId)) {
        $status = $type;
    } else {
        if ($type == COURSEMANAGER) {
            $status = 2;
        } else {
            $status = 0;
        }
    }

    $a_course_users = CourseManager :: get_user_list_from_course_code(
        $course_code,
        $sessionId,
        $limit,
        $order_by,
        $status,
        null,
        false,
        false,
        null,
        array(),
        array(),
        $active
    );

    foreach ($a_course_users as $user_id => $o_course_user) {
        if ((
            isset($_GET['keyword']) &&
            searchUserKeyword(
                $o_course_user['firstname'],
                $o_course_user['lastname'],
                $o_course_user['username'],
                $o_course_user['official_code'],
                $_GET['keyword'])
            ) || !isset($_GET['keyword']) || empty($_GET['keyword'])
        ) {

            $groupsNameList = GroupManager::getAllGroupPerUserSubscription($user_id);
            $groupsNameListParsed = [];
            if (!empty($groupsNameList)) {
                $groupsNameListParsed = array_column($groupsNameList, 'name');
            }

            $temp = array();
            if (api_is_allowed_to_edit(null, true)) {

                $userInfo = api_get_user_info($user_id);
                $photo = Display::img($userInfo['avatar_small'], $userInfo['complete_name'], [], false);

                $temp[] = $user_id;
                $temp[] = $photo;
                $temp[] = $o_course_user['official_code'];

                if ($is_western_name_order) {
                    $temp[] = $o_course_user['firstname'];
                    $temp[] = $o_course_user['lastname'];
                } else {
                    $temp[] = $o_course_user['lastname'];
                    $temp[] = $o_course_user['firstname'];
                }

                $temp[] = $o_course_user['username'];

                // Groups.
                $temp[] = implode(', ', $groupsNameListParsed);

                // Status
                $default_status = get_lang('Student');
                if ((isset($o_course_user['status_rel']) && $o_course_user['status_rel'] == 1) ||
                    (isset($o_course_user['status_session']) && $o_course_user['status_session'] == 2)
                ) {
                    $default_status = get_lang('CourseManager');
                } elseif (isset($o_course_user['is_tutor']) && $o_course_user['is_tutor'] == 1) {
                    $default_status = get_lang('Tutor');
                }

                $temp[] = $default_status;

                // Active
                $temp[] = $o_course_user['active'];

                if (!empty($extraFields)) {
                    foreach ($extraFields as $extraField) {
                        $extraFieldValue = new ExtraFieldValue('user');
                        $data = $extraFieldValue->get_values_by_handler_and_field_id(
                            $user_id,
                            $extraField['id']
                        );
                        $temp[] = $data['value'];
                    }
                }

                // User id for actions
                $temp[] = $user_id;
                $temp['is_tutor'] = isset($o_course_user['is_tutor']) ? $o_course_user['is_tutor'] : '';
                $temp['user_status_in_course'] = isset($o_course_user['status_rel']) ? $o_course_user['status_rel'] : '';
            } else {
                $userInfo = api_get_user_info($user_id);
                $userPicture = $userInfo['avatar'];

                $photo= '<img src="'.$userPicture.'" alt="'.$userInfo['complete_name'].'" width="22" height="22" title="'.$userInfo['complete_name'].'" />';

                $temp[] = $user_id;
                $temp[] = $photo;
                $temp[] = $o_course_user['official_code'];

                if ($is_western_name_order) {
                    $temp[] = $o_course_user['firstname'];
                    $temp[] = $o_course_user['lastname'];
                } else {
                    $temp[] = $o_course_user['lastname'];
                    $temp[] = $o_course_user['firstname'];
                }
                $temp[] = $o_course_user['username'];
                // Group.
                $temp[] = implode(', ', $groupsNameListParsed);

                if ($course_info['unsubscribe'] == 1) {
                    //User id for actions
                    $temp[] = $user_id;
                }
            }
            $a_users[$user_id] = $temp;
        }
    }

    return $a_users;
}


/**
 * Build the active-column of the table to lock or unlock a certain user
 * lock = the user can no longer use this account
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @param int $active the current state of the account
 * @param int $user_id The user id
 * @param string $urlParams
 *
 * @return string Some HTML-code with the lock/unlock button
 */
function active_filter($active, $urlParams, $row)
{
    $userId = api_get_user_id();
    $action = '';
    $image = '';
    if ($active == '1') {
        $action = 'AccountActive';
        $image = 'accept';
    }
    if ($active == '0') {
        $action = 'AccountInactive';
        $image = 'error';
    }
    $result = '';

    /* you cannot lock yourself out otherwise you could disable all the accounts including your own => everybody is
        locked out and nobody can change it anymore.*/
    if ($row[0] <> $userId) {
        $result = '<center><img src="'.Display::returnIconPath($image.'.png', 16).'" border="0" alt="'.get_lang(ucfirst($action)).'" title="'.get_lang(ucfirst($action)).'"/></center>';
    }

    return $result;
}

/**
 * Build the modify-column of the table
 * @param int $user_id The user id
 * @return string Some HTML-code
 */
function modify_filter($user_id, $row, $data)
{
    global $is_allowed_to_track, $charset;

    $user_id = $data[0];
    $userInfo = api_get_user_info($user_id);
    $isInvitee = $userInfo['status'] == INVITEE ? true : false;
    $course_info = $_course = api_get_course_info();
    $current_user_id = api_get_user_id();
    $sessionId = api_get_session_id();
    $type = isset($_REQUEST['type']) ? intval($_REQUEST['type']) : STUDENT;

    $result = "";
    if ($is_allowed_to_track) {
        $result .= '<a href="../mySpace/myStudents.php?'.api_get_cidreq().'&student='.$user_id.'&details=true&course='.$_course['id'].'&origin=user_course&id_session='.api_get_session_id().'" title="'.get_lang('Tracking').'">
            '.Display::return_icon('stats.png', get_lang('Tracking')).'
        </a>';
    }

    // If platform admin, show the login_as icon (this drastically shortens
    // time taken by support to test things out)
    if (api_is_platform_admin()) {
        $result .= ' <a href="'.api_get_path(WEB_CODE_PATH).'admin/user_list.php?action=login_as&user_id='.$user_id.'&sec_token='.$_SESSION['sec_token'].'">'.
            Display::return_icon('login_as.gif', get_lang('LoginAs')).'</a>&nbsp;&nbsp;';
    }

    if (api_is_allowed_to_edit(null, true)) {

        if (empty($sessionId)) {
            $isTutor = isset($data['is_tutor']) ? intval($data['is_tutor']) : 0;
            $isTutor = empty($isTutor) ? 1 : 0;

            $text = get_lang('RemoveTutorStatus');
            if ($isTutor) {
                $text = get_lang('SetTutor');
            }

            if ($isInvitee) {
                $disabled = 'disabled';
            } else {
                $disabled = '';
            }

            if ($data['user_status_in_course'] == STUDENT) {

                $result .= Display::url(
                        $text,
                        'user.php?'.api_get_cidreq(
                        ).'&action=set_tutor&is_tutor='.$isTutor.'&user_id='.$user_id.'&type='.$type,
                        array('class' => 'btn btn-default '.$disabled)
                    ).'&nbsp;';
            }
        }
        // edit
        if (api_get_setting('allow_user_course_subscription_by_course_admin') == 'true' or api_is_platform_admin()) {
            // unregister
            if ($user_id != $current_user_id || api_is_platform_admin()) {
                $result .= '<a class="btn btn-small btn-danger" href="'.api_get_self().'?'.api_get_cidreq().'&type='.$type.'&unregister=yes&user_id='.$user_id.'" title="'.get_lang('Unreg').' " onclick="javascript:if(!confirm(\''.addslashes(api_htmlentities(get_lang('ConfirmYourChoice'),ENT_QUOTES,$charset)).'\')) return false;">'.
                    get_lang('Unreg').'</a>&nbsp;';
            } else {
                //$result .= Display::return_icon('unsubscribe_course_na.png', get_lang('Unreg'),'',ICON_SIZE_SMALL).'</a>&nbsp;';
            }
        }
    } else {
        // Show buttons for unsubscribe
        if ($course_info['unsubscribe'] == 1) {
            if ($user_id == $current_user_id) {
                $result .= '<a class="btn btn-small btn-danger" href="'.api_get_self().'?'.api_get_cidreq().'&type='.$type.'&unregister=yes&user_id='.$user_id.'" title="'.get_lang('Unreg').' " onclick="javascript:if(!confirm(\''.addslashes(api_htmlentities(get_lang('ConfirmYourChoice'),ENT_QUOTES,$charset)).'\')) return false;">'.
                    get_lang('Unreg').'</a>&nbsp;';
            }
        }
    }
    return $result;
}

function hide_field()
{
    return null;
}

$default_column = 3;

$table = new SortableTable('user_list', 'get_number_of_users', 'get_user_data', $default_column);
$parameters['keyword'] = isset($_GET['keyword']) ? Security::remove_XSS($_GET['keyword']) : null;
$parameters['sec_token'] = Security::get_token();

$table->set_additional_parameters($parameters);
$header_nr = 0;
$indexList = array();
$table->set_header($header_nr++, '', false);

$indexList['photo'] = $header_nr;
$table->set_header($header_nr++, get_lang('Photo'), false);

$table->set_header($header_nr++, get_lang('OfficialCode'));
$indexList['official_code'] = $header_nr;

if ($is_western_name_order) {
    $indexList['firstname'] = $header_nr;
    $table->set_header($header_nr++, get_lang('FirstName'));
    $indexList['lastname'] = $header_nr;
    $table->set_header($header_nr++, get_lang('LastName'));
} else {
    $indexList['lastname'] = $header_nr;
    $table->set_header($header_nr++, get_lang('LastName'));
    $indexList['firstname'] = $header_nr;
    $table->set_header($header_nr++, get_lang('FirstName'));
}
$indexList['username'] = $header_nr;
$table->set_header($header_nr++, get_lang('LoginName'));
$indexList['groups'] = $header_nr;
$table->set_header($header_nr++, get_lang('GroupSingle'), false);

if (api_is_allowed_to_edit(null, true) && api_get_setting('allow_user_course_subscription_by_course_admin') == 'true') {

} else {
    $table->set_column_filter(0, 'hide_field');
}

$hideFields = api_get_configuration_value('hide_user_field_from_list');
if (!empty($hideFields)) {
    foreach ($hideFields as $fieldToHide) {
        if (isset($indexList[$fieldToHide])) {
            $table->setHideColumn($indexList[$fieldToHide]);
        }
    }
}

if (api_is_allowed_to_edit(null, true)) {
    $table->set_header($header_nr++, get_lang('Status'), false);
    $table->set_header($header_nr++, get_lang('Active'), false);
    if (api_get_setting('allow_user_course_subscription_by_course_admin') == 'true') {
        $table->set_column_filter(8, 'active_filter');
    } else {
        $table->set_column_filter(8, 'active_filter');
    }

    foreach ($extraFields as $extraField) {
        $table->set_header($header_nr++, $extraField['display_text'], false);
    }

    // Actions column
    $table->set_header($header_nr++, get_lang('Action'), false);
    $table->set_column_filter($header_nr-1, 'modify_filter');

    if (api_get_setting('allow_user_course_subscription_by_course_admin') == 'true') {
        $table->set_form_actions(array('unsubscribe' => get_lang('Unreg')), 'user');
    }
} else {
    if ($course_info['unsubscribe'] == 1) {
        $table->set_header($header_nr++, get_lang('Action'), false);
        $table->set_column_filter($header_nr-1, 'modify_filter');
    }
}

/*	Header */
if (isset($origin) && $origin == 'learnpath') {
    Display::display_reduced_header();
} else {

    if (isset($_GET['keyword']) && !empty($_GET['keyword'])) {
        $interbreadcrumb[] = array(
            "url" => "user.php?".api_get_cidreq(),
            "name" => get_lang("Users"),
        );
        $tool_name = get_lang('SearchResults');
    } else {
        $tool_name = get_lang('Users');
        $origin = 'users';
    }
    Display::display_header($tool_name, "User");
}

/*	Setting the permissions for this page */
$is_allowed_to_track = ($is_courseAdmin || $is_courseTutor);

// Tool introduction
Display::display_introduction_section(TOOL_USER, 'left');
$actions = '';
$selectedTab = 1;

if (api_is_allowed_to_edit(null, true)) {
    echo '<div class="actions">';

    switch ($type) {
        case STUDENT:
            $selectedTab = 1;
            $url = api_get_path(WEB_CODE_PATH).'user/subscribe_user.php?'.api_get_cidreq().'&type='.STUDENT;
            $icon = Display::url(
                    Display::return_icon('add-user.png', get_lang('Add'), '', ICON_SIZE_MEDIUM),
                    $url
                    );
            break;
        case COURSEMANAGER:
            $selectedTab = 2;
            $url = api_get_path(WEB_CODE_PATH).'user/subscribe_user.php?'.api_get_cidreq().'&type='.COURSEMANAGER;
            $icon = Display::url(
                    Display::return_icon('add-teacher.png', get_lang('Add'), '', ICON_SIZE_MEDIUM),
                    $url
                    );
            break;
    }

    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo $icon;
    $actions .= '<a href="user.php?'.api_get_cidreq().'&action=export&format=csv&type='.$type.'">'.
        Display::return_icon('export_csv.png', get_lang('ExportAsCSV'),'',ICON_SIZE_MEDIUM).'</a> ';
    $actions .= '<a href="user.php?'.api_get_cidreq().'&action=export&format=xls&type='.$type.'">'.
        Display::return_icon('export_excel.png', get_lang('ExportAsXLS'),'',ICON_SIZE_MEDIUM).'</a> ';

    if (api_get_setting('allow_user_course_subscription_by_course_admin') == 'true' ||
        api_is_platform_admin()
    ) {
        $actions .= '<a href="user_import.php?'.api_get_cidreq().'&action=import">'.
            Display::return_icon('import_csv.png', get_lang('ImportUsersToACourse'),'',ICON_SIZE_MEDIUM).'</a> ';
    }

    $actions .= '<a href="user.php?'.api_get_cidreq().'&action=export&format=pdf&type='.$type.'">'.
        Display::return_icon('pdf.png', get_lang('ExportToPDF'),'',ICON_SIZE_MEDIUM).'</a> ';
    echo $actions;

    echo '</div>';
    echo '<div class="col-md-6">';
    echo '<div class="pull-right">';
        // Build search-form
        $form = new FormValidator('search_user', 'get', '', '', null, FormValidator::LAYOUT_INLINE);
        $form->addText('keyword', '', false);
        $form->addButtonSearch(get_lang('SearchButton'));
        $form->display();
    echo '</div>';
    echo '</div>';
    echo '</div>';

    $allowTutors = api_get_setting('allow_tutors_to_assign_students_to_session');
    if (api_is_allowed_to_edit() && $allowTutors == 'true') {
        $actions .= ' <a class="btn btn-default" href="session_list.php?'.api_get_cidreq().'">'.
            get_lang('Sessions').'</a>';
    }
    echo '</div>';
}

echo UserManager::getUserSubscriptionTab($selectedTab);

$table->display();

if (!empty($_GET['keyword']) && !empty($_GET['submit'])) {
    $keyword_name = Security::remove_XSS($_GET['keyword']);
    echo '<br/>'.get_lang('SearchResultsFor').' <span style="font-style: italic ;"> '.$keyword_name.' </span><br>';
}

if (!isset($origin) || $origin != 'learnpath') {
    Display::display_footer();
}