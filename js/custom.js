jQuery(document).ready(function ($) {
    // Code that uses jQuery's $ can follow here.
    const fileUpload = {
        init() {
            const submitBtn = $('span[id^="ssfa_submit_upload"]');
            const chooseFilesBtn = $('input[id^="ssfa_fileup_files"]');
            $('.ssfa_add_files').html('+ Add File(s)');
            const iconHTML = '<i class="fas fa-upload"></i>';
            submitBtn.hide();
            submitBtn.css('background-color', '#44B800');
            submitBtn.html(iconHTML + ' Upload');
            submitBtn.on('click', fileUpload.observeFileList);
            chooseFilesBtn.on('click', fileUpload.observeFileList);
        },
        observeFileList(e) {
            // Select the node that will be observed for mutations
            const table = document.querySelector('div[id^="ssfa_fileup_files_container"]');
            let targetNode = table.querySelector('div[id^="ssfa-table-wrap"]');

            // Callback function to execute when mutations are observed
            const submitCallback = function (mutationsList) {
                // Use traditional 'for loops' for IE 11
                for (let mutation of mutationsList) {
                    if (mutation.type === 'childList' && mutation.target.id.includes('ssfa-table-wrap')) {
                        // add a spinner to the page to show something is about to happen
                        Woffice.frontend.start();
                        // refresh the browser window to show the newly added files
                        window.location.reload();
                    }
                }
            };

            const addFileCallback = function (mutationsList) {
                const fileCount = fileUpload.getFileCount();
                if (fileCount === 0) {
                    fileUpload.toggleBtnDisable('submit', true);
                    fileUpload.toggleBtnDisable('stage', false);
                } else {
                    fileUpload.toggleBtnDisable('submit', false);
                    fileUpload.toggleBtnDisable('stage', true);
                }
            }

            // Create an observer instance linked to the callback function
            if (e.target.id.includes("_files")) {
                const config = {
                    childList: true,
                    subtree: true,
                };
                targetNode = table;
                const observer = new MutationObserver(addFileCallback);
                observer.observe(targetNode, config);
            } else {
                const config = {
                    childList: true,
                };
                const observer = new MutationObserver(submitCallback);
                observer.observe(targetNode, config);
            }
        },
        getFileCount() {
            const fileCount = $('tr[id^="ssfa_upfile_id"]').length;
            return fileCount;
        },
        toggleBtnDisable(btn, bool) {
            const submitBtn = $('span[id^="ssfa_submit_upload"]');
            const chooseFilesBtn = $('.ssfa_add_files');
            if (btn === 'submit') {
                bool ? submitBtn.hide() : submitBtn.show();
            } else if (btn === 'stage') {
                bool ? chooseFilesBtn.hide() : chooseFilesBtn.show();
            }
        },
    };

    window.addEventListener('load', fileUpload.init);
});