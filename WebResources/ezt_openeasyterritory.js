function openURL() {

    // var eztEndpoint = 'https://democrm.easyterritory.com/APP';
    var eztEndpoint = getEZTSetting("EZT Instance URL");

    // using the user guid as the project id prevents the creation of endless hidden projects in the system that never get deleted
    // it also ensure concurrent user protection
    // normally we would get this from the form
    var userGuid = Xrm.Page.context.getUserId().replace('{', '').replace('}', '');

    if (eztEndpoint != "NoSettingReturned") {
        // comes from CRM adv find results form
        // var theFetchXml = document.getElementById('FetchXml').value;
        try {
            //get FetchXML 
            var advFind = _mainWindow.$find("advFind"), iOldMode = advFind.get_fetchMode();
            var sFetchXml = advFind.get_fetchXml();
            //get related information 
            var theFetchXml = sFetchXml;
            var LayoutXml = theFetchXml.LayoutXml;
            //var EntityName = theFetchXml.EntityName;
            //var DefaultAdvFindViewId = theFetchXml.DefaultAdvancedFindViewId;
            //var ViewId = theFetchXml.QueryId;
            //var ViewType = theFetchXml.QueryObjectType;

            //new project object
            var project = {
                id: userGuid,
                customJson: JSON.stringify({
                    type: 'fetchXml',
                    data: theFetchXml
                }),
                allowOverwrite: true,
                unlisted: true,
                label: 'Advanced Find Results'
            };

            // call ezt rest service to create a new project
            var call = $.ajax({
                url: eztEndpoint + '/REST/Project',
                type: 'POST',
                cache: false,
                contentType: 'application/json; charset=utf-8',
                data: JSON.stringify(project)
            });

            // returns newly created project id
            call.done(function (data) {

                var projectId = data.id;

                // do your window.open here using the above project id
                window.open(eztEndpoint + '/index.html?projectId=' + projectId);
            });

        } catch (err) {
            txt = "There was an error on this page.\n\n";
            txt += "Error description: " + err.message + "\n\n";
            txt += "Click OK to continue.\n\n";
            alert(txt);
        }
    }
    else {
        txt = "There was an error on this page.\n\n";
        txt += "Error description: EasyTerritory Settings entity not configured, or EZT Instance URL setting is missing.\n\n";
        txt += "Click OK to continue.\n\n";
        alert(txt);

    }
};


function getEZTSetting(settingName) {
    var clientURL = Xrm.Page.context.getClientUrl();
    var restQueryURL = clientURL + "/XRMServices/2011/OrganizationData.svc/ezt_easyterritorysettingsSet?$select=ezt_easyterritorysettingsId,ezt_Setting,ezt_SettingName&$filter=ezt_SettingName eq '" + settingName + "'";
    var retval = "NoSettingReturned";
    $.ajax({
        type: "GET",
        contentType: "application/json; charset=utf-8",
        datatype: "json",
        url: restQueryURL,
        beforeSend: function (XMLHttpRequest) {
            XMLHttpRequest.setRequestHeader("Accept", "application/json");
        },
        async: false,
        success: function (data, textStatus, xhr) {
            var result = data.d.results;
            if (result.length == 1) {
                retval = result[0].ezt_Setting;
            } else {
                retval = "NoSettingReturned";
            }

        },
        error: function (xhr, textStatus, errorThrown) {
            alert(textStatus + " " + errorThrown);
        }
    });

    return retval;
};
