## ![Ensemble Video logo](ext_chooser/css/images/logo.png) Ensemble Video Moodle Repository Plugin

__[Overview](#overview)__<br/>
__[Requirements](#req)__<br/>
__[Installing from Git](#git_install)__<br/>
__[Upgrading from Git](#git_upgrade)__<br/>
__[Installing from ZIP](#zip_install)__<br/>
__[Upgrading from ZIP](#zip_upgrade)__<br/>
__[Plugin Setup](#setup)__<br/>
__[Important Notes for 3.0](#upgrade_notes)__<br/>

### <a id="overview"></a>Overview

Along with the [Ensemble Video Moodle Filter Plugin](https://github.com/ensembleVideo/moodle-filter_ensemble),
this plugin makes it easier for Moodle users to add videos and playlists to
content without having to navigate to Ensemble Video and copy/paste complicated
embed codes.  Once setup, you should see an additional repository option under
_Insert Moodle media_ in the Moodle content editor, allowing you to choose
videos and playlists to insert from the configured Ensemble Video installation.

In addition to this documentation, also see our [Setup the Moodle Plugin](http://support.ensemblevideo.com/setup-the-moodle-plugin/)
and [Using the Moodle Plugin](http://support.ensemblevideo.com/using-the-moodle-plugin/) support articles.

### <a id="req"></a>Requirements

* Ensemble Video version of 4.3 or higher.
* Moodle version 3.0 or higher.
* Internet Explorer 9 or higher.  No known issues with other browsers.
* Depends on the [Ensemble Video Moodle Filter Plugin](https://github.com/ensembleVideo/moodle-filter_ensemble) for embed code rendering.

### <a id="git_install"></a>Installing from Git

These installation instructions are based off the strategy endorsed by Moodle
for [installing contributed extensions via Git](http://docs.moodle.org/30/en/Git_for_Administrators#Installing_a_contributed_extension_from_its_Git_repository).

    $ cd /path/to/your/moodle
    $ cd repository
    $ git clone https://github.com/ensembleVideo/moodle-repository_ensemble.git ensemble
    $ cd ensemble
    $ git checkout -b MOODLE_30_STABLE origin/MOODLE_30_STABLE

As a Moodle administrator, navigate to _Settings -> Site Administration -> Notifications_
and click _Upgrade Moodle database now_ to install the plugin.

### <a id="git_upgrade"></a>Upgrading from Git

To upgrade the plugin do the following:

    $ cd /path/to/your/moodle/repository/ensemble
    $ git pull

As a Moodle administrator, navigate to _Settings -> Site Administration -> Notifications_
and click _Upgrade Moodle database now_ to upgrade the plugin.

### <a id="zip_install"></a>Installing from ZIP

    $ wget https://github.com/ensembleVideo/moodle-repository_ensemble/archive/MOODLE_30_STABLE.zip
    $ unzip MOODLE_30_STABLE.zip
    $ mv moodle-repository_ensemble-MOODLE_30_STABLE /path/to/your/moodle/repository/ensemble

As a Moodle administrator, navigate to _Settings -> Site Administration -> Notifications_
and click _Upgrade Moodle database now_ to install the plugin.

### <a id="zip_upgrade"></a>Upgrading from ZIP

To upgrade the plugin delete the
_/path/to/your/moodle/repository/ensemble_ directory, then repeat the installation
steps above.

As a Moodle administrator, navigate to _Settings -> Site Administration -> Notifications_
and click _Upgrade Moodle database now_ to upgrade the plugin.

### <a id="setup"></a>Plugin Setup

Navigate to _Settings -> Site Administration -> Plugins -> Repositories -> Manage repositories_
and set the Ensemble Video repository to _Enabled and visible_.

Repository instances can be added site-wide or within a course context.

#### Repository Instance Settings

##### Ensemble URL
**Required**.  Must point to the application root of your Ensemble Video
installation.  If, for example, the url for your Ensemble install is
_https://cloud.ensemblevideo.com/app/library.aspx_, you would use
_https://cloud.ensemblevideo.com_.  In the case of a url like
_https://server.myschool.edu/ensemble/app/library.aspx_ you would use
_https://server.myschool.edu/ensemble_.

##### Consumer Key

**Required**.  The Consumer Key copied from the appropriate LTI configuration in
Ensemble Video -> Administration -> Institution -> LTI Configurations.

##### Shared Secret

**Required**.  The Shared Secret copied from the appropriate LTI configuration
in Ensemble Video -> Administration -> Institution -> LTI Configurations.

##### Additional Parameters (optional)

**Optional**.  Any additional parameters to be passed in the LTI tool launch in
order to override default launch and tool UI behavior.  Each value should be on
a new line and be in the format {parameter}={value}, for e.g.

    custom_ensemble_username_param=lis_person_contact_email_primary
    custom_ensemble_default_video_width=640
    custom_ensemble_video_setting_download=true

Available optional parameters:
* **custom\_ensemble\_username\_param**: Tells the launch handler to use the given LTI
launch parameter (e.g. "lis\_person\_contact\_email\_primary"), rather than the
default ("custom\_moodle\_user\_login\_id"), when performing username mapping.
* **custom\_ensemble\_username\_domain**: If the username value being mapped from
Moodle requires a domain-qualification (e.g. "username@example.edu") in order to
match the username within EV, this parameter can be set to provide that domain
(e.g. "example.edu").
* **custom\_ensemble\_default\_video\_width**: The default video width embed option is
set to the value of the given encoding.  This parameter can be passed to
override that with a specific static selected width value (must match one of the
available selection options).
* **custom\_ensemble\_video\_setting\_{setting}**: Override default selected video embed
options (e.g. "custom\_ensemble\_video\_setting\_download").  Available options and
default values are listed below.
    * **showtitle**: true
    * **autoplay**: false
    * **showcaptions**: false
    * **hidecontrols**: false
    * **socialsharing**: false
    * **annotations**: true
    * **captionsearch**: true
    * **attachments**: true
    * **links**: true
    * **metadata**: true
    * **dateproduced**: true
    * **embedcode**: false
    * **download**: false
* **custom\_ensemble\_playlist\_setting\_{setting}**:  Override default selected
playlist embed options (e.g. "custom\_ensemble\_playlist\_setting\_embedcode").
Available options and default values are listed below.
    * **layout**: 'playlist'
    * **playlistLayout\_playlistSortBy**: 'videoDate'
    * **playlistLayout\_playlistSortDirection**: 'desc'
    * **showcaseLayout\_categoryList**: true
    * **showcaseLayout\_categoryOrientation**: 'horizontal'
    * **embedcode**: false
    * **statistics**: true
    * **duration**: true
    * **attachments**: true
    * **annotations**: true
    * **links**: true
    * **credits**: true
    * **socialsharing**: false
    * **autoplay**: false
    * **showcaptions**: false
    * **dateproduced**: true
    * **audiopreviewimage**: false
    * **captionsearch**: true

### <a id="upgrade_notes"></a>Important Notes for 3.0

If you are upgrading from a previous version, there are some important changes
to be aware of.

Whereas prior versions of this plugin used a locally-hosted UI along with
proxying of EV API requests, this version leverages <a href="https://www.imsglobal.org/activity/learning-tools-interoperability">LTI</a>
in order to render a similar EV-hosted UI.  Among the advantages of this approach
is improved plugin security, the ability to immediately use UI improvements shipped
with EV upgrades/hotfixes without the additional plugin maintainence overhead,
and improvements to the authentication process.

Unlike prior versions which required and only supported two site-wide instance
configurations, one for an EV videos repository and one for an EV playlists
repository, this version provides searching of videos and playlists within a
single repository instance as well as configuration of instances at the course
and/or site level.

**Note:** As a result of the above changes, the existing repository instances
are removed on upgrade to this version and the plugin is disabled.  Re-enable
the plugin and add repository instances where appropriate.

**Note:** Prior versions allowed access to view the configured site-wide repositories
to any authenticated user.  This version limits access to configured instances to
the _coursecreator_, _teacher_, _editingteacher_ and _manager_ roles. However, this
access is <a href="https://docs.moodle.org/dev/NEWMODULE_Adding_capabilities#archetypes">not granted or removed</a>
on plugin upgrade.  Therefore it is  **highly recommended** that you explicitly
<a href="https://docs.moodle.org/30/en/Managing_roles">modify access</a> to the
_repository/ensemble:view_ capability for these and the original _user_ role.
