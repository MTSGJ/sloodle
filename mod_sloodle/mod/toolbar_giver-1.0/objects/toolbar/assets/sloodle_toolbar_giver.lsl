/*********************************************
*  sloodle_toolbar_giver
*********************************************/

// Edmund Edgar, 2012-05-12: Removing the full toolbar, just using the lite one.
// If we resurrect the full toolbar, get the old version of this script from Git.

default
{
    state_entry()
    {
        llSetText("Click to get your toolbar", <0,0,1>, 100);
    }


    touch_start(integer total_number)
    {
        integer i;
        for (i=0; i<total_number; i++) {
            llGiveInventory(llDetectedKey(i), "Sloodle Lite Toolbar v1.4.1");
        }
    }
    

    on_rez(integer par)
    {
        llResetScript();
    }
    
    
    changed(integer change) 
    {
        if (change & CHANGED_REGION_START) {
            llResetScript();
        }        
    }
}

// Please leave the following line intact to show where the script lives in Git:
// SLOODLE LSL Script Git Location: mod/toolbar_giver-1.0/objects/toolbar/assets/sloodle_toolbar_giver.lsl


