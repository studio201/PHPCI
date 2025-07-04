var testcafePlugin = ActiveBuild.UiPlugin.extend({
    id: 'build-testcafe-errors',
    css: 'col-lg-12 col-md-12 col-sm-12 col-xs-12',
    title: Lang.get('testcafe'),
    lastData: null,
    lastMeta: null,
    displayOnUpdate: false,
    box: true,
    rendered: false,

    register: function() {
        var self = this;
        var query_data = ActiveBuild.registerQuery('testcafe-data', -1, {key: 'testcafe-data'});
        var query_meta_data = ActiveBuild.registerQuery('testcafe-meta', -1, {key: 'testcafe-meta'});

        $(window).on('testcafe-data', function(data) {
            self.onUpdateData(data);
        });

        $(window).on('testcafe-meta', function(data) {
            self.onUpdateMeta(data);
        });

        $(window).on('build-updated', function() {
            if (!self.rendered) {
                self.displayOnUpdate = true;
                query_data();
                query_meta_data();
            }
        });
    },

    render: function() {
        return $('<table class="table" id="testcafe-data">' +
            '<thead>' +
            '<tr><th>'+Lang.get('codeception_suite')+'</th>' +
            '<th>'+Lang.get('codeception_feature')+'</th>' +
            '<th>'+Lang.get('codeception_time')+'</th></tr>' +
            '</thead><tbody></tbody><tfoot></tfoot></table>');
    },

    onUpdateData: function(e) {
        if (!e.queryData) {
            $('#build-testcafe-errors').hide();
            return;
        }

        this.rendered = true;
        this.lastData = e.queryData;

        var tests = this.lastData[0].meta_value;
        var tbody = $('#testcafe-data tbody');
        tbody.empty();

        if (tests.length == 0) {
            $('#build-testcafe-errors').hide();
            return;
        }

        for (var i in tests) {

            var rows = $('<tr data-toggle="collapse" data-target="#collapse'+i+'">' +
                '<td><strong>'+tests[i].suite+'</strong</td>' +
                '<td>'+tests[i].feature+'</td>' +
                '<td>'+tests[i].time+'</td>'+
                '</tr>' +
                '<tr id="collapse'+i+'" class="collapse" >' +
                '<td></td><td colspan="2">' +
                    '<small><strong>'+Lang.get('name')+':</strong> '+tests[i].name+'</small><br />' +
                    '<small><strong>'+Lang.get('file')+':</strong> '+tests[i].file+'</small><br />' +
                    (tests[i].message
                        ? '<small><strong>'+Lang.get('message')+':</strong> '+tests[i].message.replace(/(?:\r\n|\r|\n)/g, '<br/>')+'</small>'
                        : '') +
                '</td>' +
                '</tr>');

            if (!tests[i].pass) {
                rows.first().addClass('danger');
            } else {
                rows.first().addClass('success');
            }

            tbody.append(rows);
        }

        $('#build-testcafe-errors').show();
    },

    onUpdateMeta: function(e) {
        if (!e.queryData) {
            return;
        }

        $('#build-testcafe-errors').show();
        $('#build-testcafe-errors td').tooltip();

        this.lastMeta = e.queryData;

        var data = this.lastMeta[0].meta_value;
        var tfoot = $('#testcafe-data tfoot');
        tfoot.empty();

        var row = $('<tr>' +
            '<td colspan="3">' +
            Lang.get('codeception_synopsis', data.tests, data.timetaken, data.failures) +
            '</td>' +
            '</tr>');

        tfoot.append(row);
    }
});

ActiveBuild.registerPlugin(new testcafePlugin());
