<?php
// This file is part of the Sloodle project (www.sloodle.org)
/**
* Defines a class to render a view of SLOODLE course information.
* Class is inherited from the base view class.
*
* @package sloodle
* @copyright Copyright (c) 2008 Sloodle (various contributors)
* @license http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3
*
* @contributor Paul Preibisch
* @contributor Edmund Edgar
*
*/ 


define('SLOODLE_ALL_CURRENCIES_VIEW', 1);

/** The base view class */
require_once(SLOODLE_DIRROOT.'/view/base/base_view.php');
/** SLOODLE logs data structure */
require_once(SLOODLE_LIBROOT.'/course.php');
require_once(SLOODLE_LIBROOT.'/currency.php');    


/**
* Class for rendering a view of SLOODLE course information.
* @package sloodle
*/
class sloodle_view_currency extends sloodle_base_view
{
    /**
    * The Moodle course object, retrieved directly from database.
    * @var object
    * @access private
    */
    var $course = 0;

    var $can_edit = false;

    /**
    * SLOODLE course object, retrieved directly from database.
    * @var object
    * @access private
    */
    var $sloodle_course = null;

    var $sloodle_currency = null;

    /**
    * Constructor.
    */
    //function sloodle_view_currency()
    function __construct()
    {
    }


    /**
    * Check the request parameters to see which course was specified.
    */
    function process_request()
    {
        $id = required_param('id', PARAM_INT);

        if (!$this->course = sloodle_get_record('course', 'id', $id)) print_error('Could not find course.');
        $this->sloodle_course = new SloodleCourse();
        if (!$this->sloodle_course->load($this->course)) print_error(s(get_string('failedcourseload', 'sloodle')));

    }


    function process_form()
    {
        //mode is for the different editing tasks of the currency screen (add, modify, delete)
        $mode = optional_param('mode', "view", PARAM_TEXT); 

        if ( ($mode != 'view') &&  (!$this->can_edit) ) {
            print_error('Permission denied');
        }

        switch($mode) {
          case "modify":
            //get vars
            $currencyid   = required_param('currencyid', PARAM_INT);        
            $currencyname = required_param('currencyname', PARAM_TEXT);        
            $imageurl     = required_param('imageurl', PARAM_URL);        
            $displayorder = required_param('displayorder', PARAM_INT);        

            //create update object
            $currency = new stdClass();
            $currency->id = $currencyid;
            $currency->name = $currencyname;
            $currency->displayorder = $displayorder;                
            $currency->imageurl = ($imageurl != "") ? $imageurl : null;

            //update
            $result = sloodle_update_record('sloodle_currency_types',$currency);
            if (!$result) {
                $errorlink = $CFG->wwwroot."/mod/sloodle/view.php?_type=currency&id={$id}";
                print_error(get_string('general:fail','sloodle'),$errorlink);
            }

            break;

          case "add":
            //get vars
            $currencyname = required_param('currencyname', PARAM_TEXT);        
            $imageurl     = optional_param('imageurl', '', PARAM_URL);        
            $displayorder = optional_param('displayorder', 0, PARAM_INT);        

            //create update object
            $currency = new stdClass();
            $currency->name = $currencyname;
            $currency->displayorder = $displayorder;
            $currency->imageurl = ($imageurl != "") ? $imageurl : null;

            //update
            $result = sloodle_insert_record('sloodle_currency_types',$currency);
            if (!$result) {
                $errorlink = $CFG->wwwroot."/mod/sloodle/view.php?_type=currency&id={$id}";
                print_error(get_string('general:fail','sloodle'), $errorlink);
            }

            break;

          case "confirmdelete":
            $currencyid = required_param('currencyid', PARAM_INT);        
            $result = sloodle_delete_records('sloodle_currency_types', 'id', $currencyid);
            if (!$result) {
                $errorlink = $CFG->wwwroot."/mod/sloodle/view.php?_type=currency&id={$id}";
                print_error(get_string('general:fail','sloodle'), $errorlink);
            }

            // delete awards
            $roundid = 0;
            $awards = sloodle_get_records('sloodle_award_points', 'currencyid', $currencyid);
            foreach($awards as $award) {
                if ($roundid!=$award->roundid) {
                    $roundid = $award->roundid;
                    sloodle_delete_records('sloodle_award_rounds', 'id', $roundid);
                }
            }
            sloodle_delete_records('sloodle_award_points', 'currencyid', $currencyid);

            // delete grade_items
            sloodle_delete_records('grade_items', 'itemnumber', $currencyid, 'itemmodule', 'sloodle');

            break;

          default:
            break;
        }
    }


    /**
    * Check that the user is logged-in and has permission to alter course settings.
    */
    function check_permission()
    {
        // Ensure the user logs in
        require_login($this->course->id);
        if (isguestuser()) print_error(get_string('noguestaccess', 'sloodle'));
        //add_to_log($this->course->id, 'course', 'view sloodle data', '', "{$this->course->id}");
        sloodle_add_to_log($this->course->id, 'module_viewed', 'view.php', array('_type'=>'currency','id'=>$this->course->id), 'currency: view sloodle data');

        // Ensure the user is allowed to update information on this course
        //$this->course_context = get_context_instance(CONTEXT_COURSE, $this->course->id);
        $this->course_context = context_course::instance($this->course->id, IGNORE_MISSING);
        if (has_capability('moodle/course:update', $this->course_context)) $this->can_edit = true;
    }


    /**
    * Print the course settings page header.
    */
    function sloodle_print_header()
    {
        global $CFG;
        $id = required_param('id', PARAM_INT);
        $navigation = "<a href=\"{$CFG->wwwroot}/mod/sloodle/view.php?&_type=currency&mode=allcurrencies&id={$id}\">".get_string('currencies:view', 'sloodle')."</a>";
        sloodle_print_header_simple(get_string('backpack','sloodle'), "&nbsp;", $navigation, "", "", true, '', false);
    }


    /**
    * Render the view of the module or feature.
    * This MUST be overridden to provide functionality.
    */
    function render()
    { 
        $view = optional_param('view', "", PARAM_TEXT);
        $mode= optional_param('mode', "allcurrencies", PARAM_TEXT);

        switch ($mode){
          case "allcurrencies": 
            $this->render_all_currencies();
            break;

          case "editcurrency":
            $this->render_edit_currency();
            break;

          case "deletecurrency":
            $this->delete_currency();
            break;

          default:
            $this->render_all_currencies();
            break;
        }
    }


    function render_all_currencies()
    {
        global $CFG;      
        global $COURSE;                          

        $id = required_param('id', PARAM_INT);

        // Display instrutions for this page        
        echo "<br />";
        sloodle_print_box_start('generalbox boxaligncenter center  boxheightnarrow leftpara');
        echo '<div style="position:relative ">';                                                                    
        echo '<span style="position:relative;font-size:36px;font-weight:bold;">';
        echo '<img align="center" src="'.SLOODLE_WWWROOT.'/lib/media/vault48.png" width="48"/>';
        echo get_string('currency:currencies', 'sloodle');
        echo '</span>';

        echo '<span style="float:right;">';
        echo '<a  style="text-decoration:none" href="'.$CFG->wwwroot.'/mod/sloodle/view.php?_type=backpack&id='.$COURSE->id.'">';
        echo get_string('backpacks:viewbackpacks', 'sloodle').'<br />';
        echo '<img  src="'.SLOODLE_WWWROOT.'/lib/media/returnbackpacks.png"/></a>';
        echo '</span>';                                                                                                         
        echo '</div>';

        //create an html table to display the users      
        $sloodletable = new stdClass(); 

        $sloodletable->head = array(                         
            s(get_string('currencies:displayorder', 'sloodle')),
            s(get_string('currencies:icon', 'sloodle')),
            s(get_string('currencies:name', 'sloodle')),
            ""
        );

        //set alignment of table cells                                        
        $sloodletable->align = array('left','left','left');
        $sloodletable->width="95%";
        //set size of table cells
        $sloodletable->size = array('10%','5%','50%','45%','25%');            
        //get currencies 
        //sloodle_get_records($table, $field='', $value='', $sort='', $fields='*', $limitfrom='', $limitnum='')
        $currencyTypes = SloodleCurrency::FetchAll();

        foreach ($currencyTypes as $c){
            $rowData = array();
            //cell 1 - display order
            $rowData[]= $c->displayorder;
            //cell 2 - image icon
            if (isset($c->imageurl) and $c->imageurl!=null and $c->imageurl!=''){
                $rowData[] = '<img src="'.$c->imageurl.'" width ="20px" height="20px">'; 
            }
            else {
                $rowData[] = '';
            }
            //cell 3 - currency name
            $rowData[]= $c->name;
            //cell 4 - image url
            // $rowData[]= $c->imageurl;
            //cell 5 - edit action
            $editText= "<a href=\"{$CFG->wwwroot}/mod/sloodle/view.php?&";
            $editText.= "_type=currency";
            $editText.= "&currencyid=".$c->id;
            $editText.= "&currencyname=".urlencode($c->name);
            $editText.= "&mode=editcurrency";
            $editText.= "&id={$COURSE->id}";
            $editText.= "\">";

            $editText.="<img src=\"".SLOODLE_WWWROOT."/lib/media/settings.png\" height=\"32\" width=\"32\" height=\"16\" alt=\"".get_string('currencies:edit', 'sloodle')."\"/> ";
            $editText.= "</a>";

            $editText.= "&nbsp&nbsp";
            $editText.= "<a href=\"".SLOODLE_WWWROOT."/view.php?&";
            $editText.= "_type=currency";
            $editText.= "&currencyid=".$c->id;
            $editText.= "&currencyname=".urlencode($c->name);
            $editText.= "&mode=deletecurrency";
            $editText.= "&id={$COURSE->id}";
            $editText.= "\">";
            $editText.="<img src=\"".SLOODLE_WWWROOT."/lib/media/garbage.png\" height=\"32\" width=\"32\" height=\"16\" alt=\"".s(get_string('currencies:delete', 'sloodle'))."\"/> ";
            $editText.= "</a>";
            $rowData[]= $this->can_edit ? $editText : '&nbsp;';

            $sloodletable->data[]=$rowData;
        }

        sloodle_print_table($sloodletable);
        sloodle_print_box_end();

        //create an html table to display the users      
        $sloodletable = new stdClass(); 

        $sloodletable->head = array(                         
            s(get_string('currencies:displayorder', 'sloodle')),
            s(get_string('currencies:icon', 'sloodle')),
            s(get_string('currencies:name', 'sloodle')),
            s(get_string('currencies:imageurl', 'sloodle')),
            ""
        );

        //set alignment of table cells                                        
        $sloodletable->align = array('left','left','left');
        $sloodletable->width="55%";
        //set size of table cells
        $sloodletable->size = array('10%','5%','50%','45%','25%');       

        if ($this->can_edit) {
            print('<form action="" method="POST">');
            //create cells for add row
            $cells = array();
            //cell 1 -display order
            $cells[]='<input type="hidden" name="currencyid" value="null">           
            <input type="hidden" name="mode" value="add">
            <input type="hidden" name="id" value="'.$id.'">
            <input type="text" name="displayorder" size="2" value="0">';
            //cell 2 - icon - blank
            $cells[]="";
            //cell 3 - name
            $cells[]='<input type="text" name="currencyname" size="30" value="">';
            //cell 4 - imageurl
            $cells[]='<input type="text" size="100" name="imageurl" value="">';
            //cell 5- add
            $cells[]='<input type="submit" name="add" value="'.get_string('currency:addcurrency','sloodle').'">';
            $sloodletable->data[]=$cells;

            sloodle_print_box_start('generalbox boxaligncenter center boxheightnarrow leftpara');
            print "<h2><img align=\"left\" src=\"".SLOODLE_WWWROOT."/lib/media/addnew.png\" width=\"48\"/> ";
            print s(get_string('currency:addnew','sloodle'));
            print "</h2>";

            sloodle_print_table($sloodletable);

            print("</form>");
            sloodle_print_box_end();
        }
    }


    function delete_currency()
    {
        global $CFG;      
        global $COURSE;

        $id = required_param('id', PARAM_INT);
        $currencyname= optional_param('currencyname', '', PARAM_TEXT); 
        $currencyid= required_param('currencyid', PARAM_INT); 

        echo "<br />";            
        //print header box
        sloodle_print_box_start('generalbox boxaligncenter right boxwidthnarrow boxheightnarrow rightpara');
        echo "<h1 ><img align=\"left\" src=\"".SLOODLE_WWWROOT."/lib/media/vault48.png\" width=\"48\"/> ";
        echo get_string('currency:confirmdelete', 'sloodle')."</h1>";
        sloodle_print_box_end();

        //display all currencies
        sloodle_print_box_start('generalbox boxaligncenter boxwidthfull leftpara');
        print('<form action="" method="POST">');

        $c = sloodle_get_record('sloodle_currency_types','id',$currencyid);
        $sloodletable = new stdClass(); 
        //set up column headers table data
        $sloodletable->head = array(                         
            s(get_string('currencies:icon', 'sloodle')),
            s(get_string('currencies:name', 'sloodle')), 
            "&nbsp;"
        );

        $sloodletable->align = array('left','left','left');
        $sloodletable->width="95%";
        $sloodletable->size = array('10%','50%','30%');     

        //create cells for row
        $row = array();

        //cell 1 -icon
        if (isset($c->imageurl)&&!empty($c->imageurl)){
            $row[]= '<img src="'.$c->imageurl.'" width ="20px" height="20px">'; 
        }
        else {
            $row[]= " ";
        }

        //cell 2 - name
        $row[]='<input type="hidden" name="mode" value="confirmdelete">
        <input type="hidden" name="currencyid" value="'.intval($c->id).'">
        <input type="hidden" name="id" value="'.$id.'">'.s($c->name);

        //cell 4 - submit
        $row[]='<input type="submit" name="sumbit" value="'
        .s(get_string('currency:deletethiscurrency', 'sloodle')).'">';

        $sloodletable->data[]=$row;

        sloodle_print_table($sloodletable);
        sloodle_print_box_end();
    }


    function render_edit_currency()
    {
        global $CFG;      
        global $COURSE;

        $id = required_param('id', PARAM_INT);

        $currencyname= required_param('currencyname', PARAM_TEXT);
        $currencyid= required_param('currencyid', PARAM_INT);
        echo "<br />";            

        //print header box
        sloodle_print_box_start('generalbox boxaligncenter center boxwidthnarrow boxheightnarrow leftpara');

        echo "<h1 color=\"Red\"><img align=\"center\" src=\"".SLOODLE_WWWROOT."/lib/media/vault48.png\" width=\"48\"/> ";
        echo get_string('currency:editcurrency', 'sloodle')."</h1>";

        sloodle_print_box_end();

        //display all currencies
        sloodle_print_box_start('generalbox boxaligncenter boxwidthfull leftpara');

        print('<form action="" method="POST">');

        $c= sloodle_get_record('sloodle_currency_types','id',$currencyid);
        $sloodletable = new stdClass(); 
        //set up column headers table data
        $sloodletable->head = array(                         
            s(get_string('currencies:displayorder', 'sloodle')),
            s(get_string('currencies:imageurl', 'sloodle')),
            s(get_string('currencies:name', 'sloodle')),
            "&nbsp;"
        );
        $sloodletable->align = array('left','left','left');
        $sloodletable->width="95%";
        $sloodletable->size = array('10%','50%','30%','15%');     

        //create cells for row
        $row = array();
        //cell 1 -display order
        $row[]='<input type="hidden" name="currencyid" value="'.$c->id.'">           
        <input type="text" name="displayorder" size="2" value="'.$c->displayorder.'">';

        //cell 2 - imageurl
        $row[]='<input type="text" size="100" name="imageurl" value="'.$c->imageurl.'">
        <input type="hidden" name="mode" value="modify">
        <input type="hidden" name="id" value="'.$id.'">';

        //cell 3 - name
        $row[]='<input type="text" name="currencyname" size="30" value="'.$c->name.'">';

        //cell 4 - submit
        $row[]='<input type="submit" name="sumbit" value="submit">';
        $sloodletable->data[]=$row;

        sloodle_print_table($sloodletable);

        print("</form>");
        sloodle_print_box_end();
    }

}
