/* ************************************************************************

  Bibliograph. The open source online bibliographic data manager

  http://www.bibliograph.org

  Copyright:
    2003-2020 Christian Boulanger

  License:
    MIT license
    See the LICENSE file in the project's top-level directory for details.

  Authors:
    Christian Boulanger (@cboulanger) info@bibliograph.org

************************************************************************ */

/**
 * Window with information on the application
 * @asset(bibliograph/icon/bibliograph-logo-square.png)
 */
qx.Class.define("bibliograph.ui.window.TaskMonitor",
{
  extend : qx.ui.window.Window,
  type: "singleton",
  construct : function() {
    this.base(arguments);
    this.set({
      layout: new qx.ui.layout.Grow(),
      caption: this.tr("Background Tasks"),
      setshowMaximize: false,
      showMinimize: false,
      width: 350,
      height: 450
    });
    // center when it is first shown
    this.addListenerOnce("appear", () => {
      this.set();
      this.setLayoutProperties({right: 50, top: 50});
    }, this);
    qx.event.message.Bus.getInstance().subscribe("logout", this.close, this);
    let list = new qx.ui.list.List();
    var delegate = {
      // create a list item
      createItem : function() {
        let container = new qx.ui.container.Composite(new qx.ui.layout.HBox(5));
        container.add(new qx.ui.basic.Atom(), {flex:1});
        container.add(new qx.ui.indicator.ProgressBar().set({width:50}));
        return container;
      },
      bindItem : function(controller, item, id) {
        // bind label
        controller.bindProperty("name", "label", {}, item.getChildren()[0], id);
        // bind progress if any
        controller.bindProperty("progress", "visibility", {
          converter: value => value === null ? "excluded" : "visible"
        }, item.getChildren()[1], id);
        controller.bindProperty("progress", "value", {
          converter: value => Number(value)
        }, item.getChildren()[1], id);
        // show inactive tasks as disabled
        controller.bindProperty("active", "enabled", {}, item, id);
      }
    };
    list.setDelegate(delegate);
    list.setModel(this.getApplication().getTaskManager().getTasks());
    this.add(list);
    // command
    let cmd = this.__cmd = new qx.ui.command.Command("Ctrl+M");
    cmd.addListener("execute", () => {
      this.isVisible() ? this.close() : this.open();
    });
  },
  members: {
    
    /** @var qx.ui.command.Command */
    __cmd : null,
    
    /**
     * Returns the command for this window
     * @return {qx.ui.command.Command}
     */
    getCommand() {
      return this.__cmd;
    }
  }
});
