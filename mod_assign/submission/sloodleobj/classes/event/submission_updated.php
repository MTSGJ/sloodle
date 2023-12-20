﻿<?php
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
 * The assignsubmission_sloodleobj submission_updated event.
 *
 * @package    assignsubmission_sloodleobj
 * @copyright  2014 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_sloodleobj\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The assignsubmission_sloodleobj submission_updated event class.
 *
 * @package    assignsubmission_sloodleobj
 * @since      Moodle 2.7
 * @copyright  2014 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_updated extends \mod_assign\event\submission_updated
{
    /**
     * Init method.
     */
    protected function init()
    {
        parent::init();
        $this->data['objecttable'] = 'assignsubmission_sloodleobj';
    }


    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description()
    {
        $descriptionstring = "The user with id '$this->userid' updated a object submission with " .
            "'{$this->other['objectsubmissioncount']}' object/s in the assignment with course module id " .
            "'$this->contextinstanceid'";
        if (!empty($this->other['groupid'])) {
            $descriptionstring .= " for the group with id '{$this->other['groupid']}'.";
        }
        else {
            $descriptionstring .= ".";
        }

        return $descriptionstring;
    }


    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data()
    {
        parent::validate_data();
        if (!isset($this->other['objectsubmissioncount'])) {
            throw new \coding_exception('The \'objectsubmissioncount\' value must be set in other.');
        }
    }


    public static function get_objectid_mapping()
    {
        // No mapping available for 'assignsubmission_sloodleobj'.
        return array('db' => 'assignsubmission_sloodleobj', 'restore' => \core\event\base::NOT_MAPPED);
    }
}