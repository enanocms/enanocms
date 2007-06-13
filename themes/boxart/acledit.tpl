<!-- VAR acl_field_begin -->
<div class="tblholder">
  <table border="0" cellspacing="1" cellpadding="4" style="width: 100%;">
    <tr>
      <th></th>
      <th style='cursor: pointer;' title="Click to change all columns" onclick="__aclSetAllRadios('1');">Deny</th>
      <th style='cursor: pointer;' title="Click to change all columns" onclick="__aclSetAllRadios('2');">Disallow</th>
      <th style='cursor: pointer;' title="Click to change all columns" onclick="__aclSetAllRadios('3');">Wiki mode</th>
      <th style='cursor: pointer;' title="Click to change all columns" onclick="__aclSetAllRadios('4');">Allow</th>
    </tr>
<!-- ENDVAR acl_field_begin -->
<!-- VAR acl_field_item -->
    <tr>
      <td class="{ROW_CLASS}">{FIELD_DESC}</td>
      <td class="{ROW_CLASS}" style="text-align: center;"><input type="radio" value="1" name="{FIELD_NAME}" {FIELD_DENY_CHECKED} /></td>
      <td class="{ROW_CLASS}" style="text-align: center;"><input type="radio" value="2" name="{FIELD_NAME}" {FIELD_DISALLOW_CHECKED} /></td>
      <td class="{ROW_CLASS}" style="text-align: center;"><input type="radio" value="3" name="{FIELD_NAME}" {FIELD_WIKIMODE_CHECKED} /></td>
      <td class="{ROW_CLASS}" style="text-align: center;"><input type="radio" value="4" name="{FIELD_NAME}" {FIELD_ALLOW_CHECKED} /></td>
    </tr>
<!-- ENDVAR acl_field_item -->
<!-- VAR acl_field_end -->
    <tr>
      <td colspan="5" class="row3">
        <p><b>Permission types:</b></p>
        <ul>
          <li><b>Allow</b> means that the user is allowed to access the item</li>
          <li><b>Wiki mode</b> means the user can access the item if wiki mode is active (per-page wiki mode is taken into account)</li>
          <li><b>Disallow</b> means the user is denied access unless something allows it.</li>
          <li><b>Deny</b> means that the user is denied access to the item. This setting overrides all other permissions.</li>
        </ul>
      </td>
    </tr>
  </table>
</div>
<!-- ENDVAR acl_field_end -->

