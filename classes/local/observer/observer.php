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
 * Block XP observer.
 *
 * @package    block_xp
 * @copyright  2014 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_xp\local\observer;
defined('MOODLE_INTERNAL') || die();

/**
 * Block XP observer class.
 *
 * @package    block_xp
 * @copyright  2014 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Act when a course is deleted.
     *
     * @param  \core\event\course_deleted $event The event.
     * @return void
     */
    public static function course_deleted(\core\event\course_deleted $event) {
        global $DB;

        // Clean up the data that could be left behind.
        $conditions = array('courseid' => $event->objectid);
        $DB->delete_records('block_xp', $conditions);
        $DB->delete_records('block_xp_config', $conditions);
        $DB->delete_records('block_xp_filters', $conditions);
        $DB->delete_records('block_xp_log', $conditions);

        // Delete the files.
        $fs = get_file_storage();
        $fs->delete_area_files($event->contextid, 'block_xp', 'badges');
    }

    /**
     * Observe all events.
     *
     * @param \core\event\base $event The event.
     * @return void
     */
    public static function catch_all(\core\event\base $event) {
        global $CFG;

        static $allowedcontexts = null;
        if ($allowedcontexts === null) {
            $allowedcontexts = array(CONTEXT_COURSE, CONTEXT_MODULE);
            if (isset($CFG->block_xp_context) && $CFG->block_xp_context == CONTEXT_SYSTEM) {
                $allowedcontexts[] = CONTEXT_SYSTEM;
            }
        }

        // We can't use empty if statements...
        $pleaselinter = false;

        if ($event->component === 'block_xp') {
            // Skip own events.
            $pleaselinter = true;
        } else if (!$event->userid || isguestuser($event->userid) || is_siteadmin($event->userid)) {
            // Skip non-logged in users and guests.
            $pleaselinter = true;
        } else if ($event->anonymous) {
            // Skip all the events marked as anonymous.
            $pleaselinter = true;
        } else if (!in_array($event->contextlevel, $allowedcontexts)) {
            // Ignore events that are not in the right context.
            $pleaselinter = true;
        } else if ($event->edulevel !== \core\event\base::LEVEL_PARTICIPATING) {
            // Ignore events that are not participating.
            $pleaselinter = true;
        } else if (!has_capability('block/xp:earnxp', $event->get_context(), $event->userid)) {
            // Skip the events if the user does not have the capability to earn XP.
            $pleaselinter = true;
        } else {
            // Keep the event, and proceed.
            $manager = \block_xp\dr::get()->get_manager($event->courseid);
            $manager->capture_event($event);
        }

    }

}