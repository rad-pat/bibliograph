/* ************************************************************************

  Bibliograph: Online Collaborative Reference Management

   Copyright:
     2007-2014 Christian Boulanger

   License:
     LGPL: http://www.gnu.org/licenses/lgpl.html
     EPL: http://www.eclipse.org/org/documents/epl-v10.php
     See the LICENSE file in the project's top-level directory for details.

   Authors:
     * Christian Boulanger (cboulanger)

************************************************************************ */

/**
 *
 */
qx.Class.define("bibliograph.ui.item.DuplicatesView",
{
  extend : qx.ui.container.Composite,

  /*
   *****************************************************************************
   PROPERTIES
   *****************************************************************************
   */
  properties :
  {
    /**
     * The number of potential duplicates of the current reference
     */
    numberOfDuplicates :
    {
      check : "Number",
      nullable : false,
      init : 0,
      event : "changeNumberOfDuplicates"
    }
  },

  /*
   *****************************************************************************
   MEMBERS
   *****************************************************************************
   */
  members :
  {
    duplicatesTable : null,


    /**
     * TODOC
     *
     * @return {void}
     */
    _on_appear : function()
    {
      var app = this.getApplication();
      if (app.getModelId() !== this.__modelId)
      {
        this.__modelId = app.getModelId();
        this.reloadData();
      }
    },


    /*
    ---------------------------------------------------------------------------
       API METHODS
    ---------------------------------------------------------------------------
    */

    /**
     * TODOC
     *
     * @return {void}
     */
    reloadData : function()
    {
      var app = this.getApplication();
      if ( !app.getModelId()) {
        return;
      }
      var id = app.getModelId();
      qx.event.Timer.once(function()
      {
        if (id != app.getModelId())return;
        this._reloadData();
      }, this, 500);
    },

    /**
     * TODOC
     *
     * @return {void}
     */
    _reloadData : function()
    {
      var app = this.getApplication();
      this.duplicatesTable.getTableModel().setData([]);
      this.setEnabled(false);
      app.getRpcManager().execute(
          "bibliograph.reference", "getDuplicatesData",
          [app.getDatasource(), app.getModelId()],
          function(data) {
            this.setEnabled(true);
            this.duplicatesTable.getTableModel().setData(data);
            this.setNumberOfDuplicates(data.length);
          }, this);
    },


    /**
     * TODOC
     *
     * @return {var} TODOC
     */
    getSelectedRefIds : function()
    {
      var selectedRefIds = [];
      var selectionModel = this.duplicatesTable.getSelectionModel();
      var tableModel = this.duplicatesTable.getTableModel();
      selectionModel.iterateSelection(function(index) {
        selectedRefIds.push(tableModel.getValue(0, index));
      });
      return selectedRefIds;
    },

    /**
     * TODOC
     *
     * @return {void}
     */
    _deleteDuplicate : function()
    {
      var app = this.getApplication();
      var selectedRefIds = this.getSelectedRefIds();
      if (!selectedRefIds.length)return;

      app.showPopup(this.tr("Processing request..."));
      app.getRpcManager().execute(
          "bibliograph.reference", "removeReferences",
          [app.getDatasource(), null, null, selectedRefIds],
          function() {
            app.hidePopup();
            this.reloadData();
          }, this);
    },

    /**
     * Displays the selected duplicate
     * @private
     */
    _displayDuplicate : function()
    {
      var app = this.getApplication();
      var selectedRefIds = this.getSelectedRefIds();
      if (!selectedRefIds.length)return;
      var id= selectedRefIds[0];
      app.setQuery("id="+id);
      app.setModelId(id);
      qx.lang.Function.delay(function(){
        app.setItemView("referenceEditor-recordInfo");
      },100);

    },


    endOfFile : true
  }
});
