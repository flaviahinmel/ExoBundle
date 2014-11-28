function search_user_ajax(ujm_question_share_search_user, page) {
    //"use strict";
    //alert($("#search-user-txt").val());

    var search = $("#search-user-txt").val();
    var qId = document.getElementById('QID').innerHTML;

    $.ajax({
        type: "GET",
        url: ujm_question_share_search_user,
        data: {
            search: search,
            page: page,
            qId: qId
        },
        cache: false,
        success: function (data) {
            $("#searchUserList").html(data);
        }
    });
}

function newQuestion(url) {
    $.ajax({
        type: "POST",
        url: url,
        cache: false,
        success: function (data) {
            displayNewQuestionForm(data);
        }
    });
}
function displayNewQuestionForm(data) {
    $('body').append(data);
}