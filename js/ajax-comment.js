/*
 * Let's begin with validation functions
 */
jQuery.extend(jQuery.fn, {
    /*
     * check if field value lenth more than 1 symbols ( for name and comment )
     */
    validate: function () {
        if (jQuery(this).val().length < 1) {
            jQuery(this).addClass('error');
            return false
        } else {
            jQuery(this).removeClass('error');
            return true
        }
    },
    /*
     * check if email is correct
     * add to your CSS the styles of .error field, for example border-color:red;
     */
    validateEmail: function () {
        var emailReg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/,
            emailToValidate = jQuery(this).val();
        if (!emailReg.test(emailToValidate) || emailToValidate == "") {
            jQuery(this).addClass('error');
            return false
        } else {
            jQuery(this).removeClass('error');
            return true
        }
    },
});

jQuery(function ($) {
    const commentForm = $('#comment-form');
    const commentFormBtn = $('form#comment-form button[type="submit"]');
    const submitBtn = $('input#submit');
    const detachedHTML = commentFormBtn.html();

    // check to see if the refreshed page is for a previous reply
    const isReply = window.location.href.includes('?reply');
    // if (isReply) {
    commentForm.hide();
    toggleMainCommentHtml();
    // }

    function addCommentForm(e) {
        e.preventDefault();
        let url;
        if (e.target.id === 'leave-main-comment') {
            $(e.target).parent().append(commentForm);
            url = location.protocol + '//' + location.host + location.pathname + '#project-content-comments';
        } else {
            $(this).parent().append(commentForm);
            url = e.target.href;
        }
        window.history.pushState({}, "", url);
        commentForm.show();
        submitBtn.hide(); // hides weird extra button
        commentFormBtn.html(detachedHTML); // adds the OG button text back
        $('#comment-form').submit(handleSubmit);
    }

    function toggleMainCommentHtml() {
        const leaveMainCommentBtn = `
            <button
                rel="nofollow"
                id="leave-main-comment"
                class="btn btn-default btn-sm"
            >
                <span class="material animate" style="height: 122px; width: 122px; top: -56px; left: 17.5625px;"></span>
                Leave a Comment
            </button>
        `;
        $('#respond').html(leaveMainCommentBtn);
        $('#leave-main-comment').on('click', function (e) {
            e.preventDefault();
            addCommentForm(e);
        });
    }

    $('.reply a').on('click', addCommentForm);

    /*
     * On comment form submit
     */
    $('#comment-form').submit(handleSubmit);

    function handleSubmit() {
        // define some vars
        var button = $('form#comment-form button[type="submit"]'), // submit button
            respond = $('#respond'), // comment form container
            commentlist = $('.comment-list'), // comment list container
            cancelreplylink = $('#cancel-comment-reply-link');

        // if user is logged in, do not validate author and email fields
        if ($('#author').length)
            $('#author').validate();

        if ($('#email').length)
            $('#email').validateEmail();

        // validate comment in any case
        $('#comment').validate();

        // if comment form isn't in process, submit it
        if (!button.hasClass('loadingform') && !$('#author').hasClass('error') && !$('#email').hasClass('error') && !$('#comment').hasClass('error')) {
            const data = $(this).serialize();
            console.log({
                data
            })
            const regEx = new RegExp(/(?:\&)comment_parent=(.*?)(?=\&)/)
            const urlParams = new URLSearchParams(window.location.search);
            let parentCommentId;
            let newDataString;
            if (!!urlParams.toString()) {
                parentCommentId = JSON.parse(urlParams.toString().split('=')[1]);
                newDataString = data.replace(regEx, `&comment_parent=${parentCommentId}`);
                console.log({
                    newDataString
                })
            }

            // ajax request
            $.ajax({
                type: 'POST',
                url: misha_ajax_comment_params.ajaxurl, // admin-ajax.php URL
                data: (newDataString ? newDataString : data) + '&action=ajaxcomments', // send form data + action parameter
                beforeSend: function (data, xhr, url) {
                    // what to do just after the form has been submitted
                    button.addClass('loadingform').html('Posting...');
                },
                error: function (request, status, error) {
                    console.error(error);
                    if (status == 500) {
                        alert('Error while adding comment');
                    } else if (status == 'timeout') {
                        alert('Error: Server doesn\'t respond.');
                    } else {
                        // process WordPress errors
                        var wpErrorHtml = request.responseText.split("<p>"),
                            wpErrorStr = wpErrorHtml[1].split("</p>");

                        alert(wpErrorStr[0]);
                    }
                },
                success: function (data, textStatus, jqXHR) {
                    // define some vars
                    const dataArray = JSON.parse(data);
                    let commentHTML = dataArray[0];
                    const commentId = dataArray[1];

                    const isReply = window.location.href.includes('?reply');

                    // if this post already has comments
                    if (commentlist.length > 0) {
                        // if in reply to another comment
                        if (isReply) {
                            const urlParams = new URLSearchParams(window.location.search);
                            const parentCommentId = JSON.parse(urlParams.toString().split('=')[1]);
                            const parentComment = commentlist.find(`li#comment-${parentCommentId}`);
                            // if the other replies exist
                            if (parentComment.children('.children').length) {
                                parentComment.children('.children').append(commentHTML);
                            } else {
                                // if no replies, add <ol class="children">
                                commentHTML = '<ol class="children">' + commentHTML + '</ol>';
                                parentComment.append(commentHTML);
                            }
                            // close respond form
                            cancelreplylink.trigger("click");
                        } else {
                            // simple comment
                            commentlist.append(commentHTML);
                        }
                    } else {
                        // if no comments yet
                        commentHTML = '<ol class="comment-list">' + commentHTML + '</ol>';
                        respond.before($(commentHTML));
                    }
                    // clear textarea field
                    $('#comment').val('');
                    $('.comment-author.vcard img').height(75);
                },
                complete: function () {
                    // what to do after a comment has been added
                    button.removeClass('loadingform');
                    commentForm.hide();
                    toggleMainCommentHtml();
                }
            });
        }
        return false;
    }
});