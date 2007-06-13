<div class="mdg-comment" style="padding: 0px;">
  <table border="0" width="100%" cellspacing="1" cellpadding="4">
    <tr>
      <th colspan="2" style="text-align: left;">{DATETIME}</th>
    </tr>
    <tr>
      <td style="width: 120px; height: 100%;" rowspan="4" valign="top" class="row1">
        <table border="0" width="100%" style="height: 100%;" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top" class="row1">
              <b>{NAME}</b><br />
              <small>{USER_LEVEL}</small>
            </td>
          </tr>
          <tr>
            <td valign="bottom" class="row1">
              {SEND_PM_LINK} {ADD_BUDDY_LINK}
            </td>
          </tr>
        </table>
      </td>
      <td class="row2">
        <b>Subject:</b> <span id="subject_{ID}">{SUBJECT}</span>
      </td>
    </tr>
    <tr>
      <td class="row3">
        <div id="comment_{ID}">{DATA}</div>
        <!-- BEGIN signature -->
          <hr style="margin-left: 1em; width: 200px;" />
          {SIGNATURE}
        <!-- END signature -->
      </td>
    </tr>
    <!-- BEGIN can_edit -->
    <tr>
      <td class="row2">
        [ {EDIT_LINK} | {DELETE_LINK} ]
      </td>
    </tr>
    <!-- END can_edit -->
    <!-- BEGIN auth_mod -->
    <tr>
      <td class="row1">
        <b>Moderation options:</b> {MOD_APPROVE_LINK} {MOD_DELETE_LINK}
      </td>
    </tr>
    <!-- END auth_mod -->
  </table>
</div>
<br />
