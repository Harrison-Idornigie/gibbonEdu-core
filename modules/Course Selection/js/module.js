/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

jQuery(function($){

    $(window).on('load', function (e) {
        $('.courseProgressByBlock').each(updateSelectionProgress);
    });

    $('.courseChoice[type=checkbox]').change(updateSelectionProgress);

    $('.courseStatusSelect').change(updateSelectionStatus);


    function updateSelectionProgress() {
        var blockID = $(this).data('block');

        checkDuplicates(this);

        updateBlockProgress(blockID);

        updateOfferingProgress();
    }

    function checkDuplicates(checkbox) {
        var courseID = $(checkbox).data('course');

        var duplicate = $('.courseChoice[type=checkbox][data-course='+courseID+']:checked');

        if (duplicate.length > 1) {
            alert('This course has already been selected.');
            $(checkbox).prop('checked', false);
            $(checkbox).prop('disabled', false);
        }

        var status = $(checkbox).parent().find('.courseStatusSelect option:selected').val();
        if ($(checkbox).prop('checked') == true) {
            if (status == '' || status == 'Recommended') {
                status = 'Requested';
            }

            $(checkbox).parent().attr('data-status', status);
        } else {
            if (status != 'Recommended') {
                $(checkbox).parent().attr('data-status', '');
                status = '';
            }
        }

        $(checkbox).parent().find('.courseStatusSelect').val(status);

    }

    function updateBlockProgress(blockID) {
        var choices = $('#selectionChoices').find('.courseBlock'+blockID);

        var choicesSelected = $('#selectionChoices').find('.courseBlock'+blockID+':checked');

        var progressDiv = $('.courseProgressByBlock[data-block="'+blockID+'"]');
        var progressInfo = '';

        var min = progressDiv.data('min');
        var max = progressDiv.data('max');

        if (min == undefined) min = 0;
        if (max == undefined) max = 1;

        if (choicesSelected.length >= min && choicesSelected.length <= max) {
            //progressDiv.html('Okay');
            progressDiv.addClass('complete');
            progressDiv.find('.invalid').hide();

            if (choicesSelected.length > 0) {
                progressDiv.find('.valid').show();
            } else {
                progressDiv.find('.valid').hide();
            }

            if (max > min && choicesSelected.length < max) progressInfo += 'Select up to '+max;
            else progressInfo += 'Complete';

            progressDiv.find('.valid').prop('title', progressInfo);
        } else {
            //progressDiv.html('Select '+(min - choicesSelected.length));
            progressDiv.removeClass('complete');
            progressDiv.find('.valid').hide();
            progressDiv.find('.invalid').show();

            if (min > 0) progressInfo += 'Select '+(min - choicesSelected.length);
            if (max > min) progressInfo += ' (up to '+max+')';
            progressDiv.find('.invalid').prop('title', progressInfo);
        }


        if (choicesSelected.length >= max) {
            choices.find('.courseChoice:not([data-locked="true"])').each(function() {
                $(this).data('full', true);
                if ($(this).prop('checked') == false) {
                    $(this).prop('disabled', true);
                }
            });
        } else {
            choices.find('.courseChoice:not([data-locked="true"])').each(function() {
                $(this).data('full', false);
                $(this).prop('disabled', false);
            });
        }
    }

    function updateOfferingProgress() {
        var progressDiv = $('.courseProgressByOffering');

        var min = progressDiv.data('min');
        var max = progressDiv.data('max');

        var choices = $('#selectionChoices').find('.courseChoice');
        var choicesSelected = $('#selectionChoices').find('.courseChoice:checked');

        var blocks = $('#selectionChoices').find('.courseProgressByBlock');
        var blocksComplete = $('#selectionChoices').find('.courseProgressByBlock.complete');

        if (choicesSelected.length >= min) {
            progressDiv.find('.continue').hide();

            if (blocksComplete.length >= blocks.length && choicesSelected.length <= max) {
                progressDiv.find('.invalid').hide();
                progressDiv.find('.valid').show();
            } else {
                progressDiv.find('.valid').hide();
                progressDiv.find('.invalid').show();
            }
        } else {
            progressDiv.find('.valid').hide();
            progressDiv.find('.invalid').hide();
            progressDiv.find('.continue').show();
        }

        if (choicesSelected.length >= max) {
            choices.find('.courseChoice:not([data-locked="true"])').each(function() {
                if ($(this).prop('checked') == false) {
                    $(this).prop('disabled', true);
                }
            });
        } else {
            choices.find('.courseChoice:not([data-locked="true"])').each(function() {
                if ($(this).data('full') == false) {
                    $(this).prop('disabled', false);
                }
            });
        }

        //progressDiv.html(text);
        progressDiv.find('.complete').css('width', ( Math.min(choicesSelected.length / min, 1.0)*100)+"%" );
    }

    function updateSelectionStatus() {

        var status = $('option:selected', this).val();

        $(this).parent().attr('data-status', status);

        var isChecked = (status == 'Required' || status == 'Approved' || status == 'Requested' || status == 'Selected');
        $(this).parent().find('.courseChoice').prop('checked', isChecked);

        var isLocked = (status == 'Required' || status == 'Approved');
        $(this).parent().find('.courseChoice').prop('disabled', isLocked);
        $(this).parent().find('.courseChoice').attr('data-locked', isLocked);

        $(this).parent().find('.courseChoice[type=checkbox]').change();
    }


});

function offeringBlockOrderSave(courseSelectionOfferingID, modpath) {
    var blocklist = new Array();
    $('.offeringBlockID').each(function() {
        blocklist.push($(this).val());
    });
    //console.log(blocklist);
    $.ajax({
        url: modpath + "/offerings_manage_block_orderAjax.php",
        data: {
            courseSelectionOfferingID: courseSelectionOfferingID,
            blocklist: JSON.stringify(blocklist)
        },
        type: 'POST',
        success: function(data) {
            //console.log(data);
        }
    });
}

function courseSelectionApproveAll(gibbonPersonIDStudent) {

    $('.courseSelectionApproval[name='+gibbonPersonIDStudent+']').each(function(){
        if ($(this).prop('checked') == false) {
            $(this).prop('checked', true);
            $(this).change();
        }
    });
}

function courseSelectionApprovalSave(checkbox, courseSelectionOfferingID, modpath) {
    $.ajax({
        url: modpath + "approval_approveAjax.php",
        data: {
            courseSelectionOfferingID: courseSelectionOfferingID,
            gibbonPersonIDStudent: $(checkbox).attr('name'),
            courseSelectionChoiceID: $(checkbox).val(),
            status: $(checkbox).prop('checked'),
        },
        type: 'POST',
        success: function(data) {
            if (data == 1) {
                if ($(checkbox).prop('checked') == true) {
                    $(checkbox).parent().attr('data-status', 'Approved');
                } else {
                    $(checkbox).parent().attr('data-status', '');
                }
            } else {
                $(checkbox).parent().attr('data-status', '');
                $(checkbox).prop('checked', false);
                alert('There was an error saving this approval. Please reload or try again later.');
            }
        }
    });
}

function checkTimetablingEngineStatus(modpath) {
    $.ajax({
        url: modpath + "tt_engineAjax.php",
        data: {},
        type: 'POST',
        success: function(data) {
            if (data == 1) {
                console.log('Waiting...');
                window.setTimeout(checkTimetablingEngineStatus(modpath), 1000);
            } else {
                console.log('Complete');
                window.location.reload();
            }
        }
    });
}
