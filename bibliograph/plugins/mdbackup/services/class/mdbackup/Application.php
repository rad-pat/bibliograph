<?php
/* ************************************************************************

   Bibliograph: Collaborative Online Reference Management

   http://www.bibliograph.org

   Copyright:
     2007-2014 Christian Boulanger

   License:
     LGPL: http://www.gnu.org/licenses/lgpl.html
     EPL: http://www.eclipse.org/org/documents/epl-v10.php
     See the LICENSE file in the project's top-level directory for details.

   Authors:
     * Chritian Boulanger (cboulanger)

************************************************************************ */
require_once( __DIR__ . "/__init__.php");

qcl_import("bibliograph_Application");
qcl_import("qcl_application_plugin_IPluginApplication");

class mdbackup_Application
  extends bibliograph_Application
  implements qcl_application_plugin_IPluginApplication
{}
