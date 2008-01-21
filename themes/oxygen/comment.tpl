<div class="tblholder">
  <table border="0" width="100%" cellspacing="1" cellpadding="4">
    <tr>
      <th colspan="2" style="text-align: left;">{DATETIME}</th>
    </tr>
    <tr>
      <td style="width: 120px; height: 100%;" rowspan="4" valign="top" class="row1<!-- BEGIN is_friend --> row1_green<!-- END is_friend --><!-- BEGIN is_foe --> row1_red<!-- END is_foe -->">
        <table border="0" width="100%" style="height: 100%;" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top" class="row1<!-- BEGIN is_friend --> row1_green<!-- END is_friend --><!-- BEGIN is_foe --> row1_red<!-- END is_foe -->">
              <b>{NAME}</b><br />
              <small>{USER_LEVEL}</small>
              <!-- BEGIN user_has_avatar -->
              <div class="avatar">
                <a href="{USERPAGE_LINK}">
                  <img alt="{AVATAR_ALT}" src="{AVATAR_URL}" style="border-width: 0px;" />
                </a>
              </div>
              <!-- END user_has_avatar -->
            </td>
          </tr>
          <tr>
            <td valign="bottom" class="row1<!-- BEGIN is_friend --> row1_green<!-- END is_friend --><!-- BEGIN is_foe --> row1_red<!-- END is_foe -->">
              {SEND_PM_LINK} {ADD_BUDDY_LINK}
            </td>
          </tr>
        </table>
      </td>
      <td class="row2">
        <b>{lang:comment_lbl_subject}</b> <span id="subject_{ID}">{SUBJECT}</span>
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
        <b>{lang:comment_lbl_mod_options}</b> {MOD_APPROVE_LINK} {MOD_DELETE_LINK} | {MOD_IP_LINK}
      </td>
    </tr>
    <!-- END auth_mod -->
  </table>
</div>
<br />
