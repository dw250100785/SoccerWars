Vue.component('matches', {
    template: '#matches',

    data: function() {
        return {
            loaded: false,
            matches: null
        }
    },

    ready: function(){
        var self = this;
        app.setTitle("Matches");

        function cycle() {
            $.get(API_URL + '/matches')
                .done(function (response) {
                    console.log(response);
                    self.loaded = true;
                    self.matches = response;
                })
                .fail(function (response) {
                    var message = response.responseJSON;
                    // notification error
                });
            setTimeout(cycle, 10000);
        }
        cycle();
    },

    methods: {
        go: function(id) {
            app.route_id = id;
            window.location.hash = '/match/' + id;
        }
    }
});