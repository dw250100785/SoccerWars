/* Global variables
 ******************************************************************************/
var API_URL = 'https://api.soccerwars.xyz';
var TOKEN = null;


/* Check if user has obtained a token from the login page
 ******************************************************************************/
if (Cookies.get('token')) {
    // If the cookie is set, login was successful and the user is redirected
    TOKEN = Cookies.get('token');
    if (!window.location.hash)
        window.location.hash = '#!/dashboard';
} else {
    // If not, redirect him back to login page
    window.location.href = '/';
}


/* Set default headers to be sent with every request
 ******************************************************************************/
$.ajaxSetup({
    beforeSend: function(xhr) {
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('Token', TOKEN);
    }
});


/* Main application
 ******************************************************************************/
Vue.use(VueRouter);

var app = new Vue({
    el: '#app',

    data: {
        user: {
            avatar: {
                big: ''
            }
        },
        title: null,
        isMaximized: false,
    },

    ready: function () {

        $.get(API_URL + '/me')
            .done(function (response) {
                console.log(response);
                app.user = response;
            })
            .fail(function (response) {
                console.log(response.responseJSON);
            });
    },

    methods: {

        // Set new title with animation
        setTitle: function (title) {
            app.title = title;
            scrambleText("#frame h1");
        },

        // Maximize the central section
        maximize: function () {
            app.isMaximized = true;
            $("#left").velocity({marginLeft: -150});
            $("#right").velocity({marginRight: -150});
            $("#middle").css("border-radius", "50% / 2%");
        },

        // Restore the central section
        minimize: function () {
            app.isMaximized = false;
            $("#left").velocity({marginLeft: 0});
            $("#right").velocity({marginRight: 0});
            $("#middle").css("border-radius", "50% / 3%");
        },

        // Logout and expire the token cookie
        logout: function () {
            Cookies.expire('token');
            window.location.href = '/';
        }

    }
});


/* Custom filters
 ******************************************************************************/
// Filters a list of objects which have a date span in the future
Vue.filter('future', function (value) {
    var newValues = [];
    $.each(value, function () {
        if (moment().isBefore(this.start_time))
            newValues.push(this);
    });
    return newValues;
});

// Filters a list of objects which have a date span in the past
Vue.filter('past', function (value) {
    var newValues = [];
    $.each(value, function () {
        if (moment().isAfter(this.end_time))
            newValues.push(this);
    });
    return newValues;
});

// Filters a list of objects which have a date span in the present
Vue.filter('present', function (value) {
    var newValues = [];
    $.each(value, function () {
        if (moment().isBetween(this.start_time, this.end_time))
            newValues.push(this);
    });
    return newValues;
});

// Filters a date to show relative time remaining or elapsed
Vue.filter('from_now', function (value) {
    value = moment(value).fromNow();
    return value;
});


/* Custom functions
 ******************************************************************************/
// Override setInterval function to use seconds and an option to run immediately
var originalSetInterval = window.setInterval;
window.setInterval = function(fn, delay, runImmediately) {
    if(runImmediately) fn();
    return originalSetInterval(fn, delay);
};