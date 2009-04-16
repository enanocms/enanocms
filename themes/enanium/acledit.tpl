<!-- VAR acl_field_begin -->
<div class="tblholder">
  <table border="0" cellspacing="1" cellpadding="4" style="width: 100%;">
    <tr>
      <th></th>
      <th style='cursor: pointer;' title="Click to change all columns" onclick="__aclSetAllRadios('i');">{lang:acl_lbl_field_inherit}</th>
      <th style='cursor: pointer;' title="Click to change all columns" onclick="__aclSetAllRadios('1');">{lang:acl_lbl_field_deny}</th>
      <th style='cursor: pointer;' title="Click to change all columns" onclick="__aclSetAllRadios('2');">{lang:acl_lbl_field_disallow}</th>
      <th style='cursor: pointer;' title="Click to change all columns" onclick="__aclSetAllRadios('3');">{lang:acl_lbl_field_wikimode}</th>
      <th style='cursor: pointer;' title="Click to change all columns" onclick="__aclSetAllRadios('4');">{lang:acl_lbl_field_allow}</th>
    </tr>
<!-- ENDVAR acl_field_begin -->
<!-- VAR acl_field_item -->
    <tr>
      <td class="{ROW_CLASS}">{FIELD_DESC}</td>
      <td class="{ROW_CLASS}" style="text-align: center;"><input type="radio" value="i" name="{FIELD_NAME}" {FIELD_INHERIT_CHECKED} /></td>
      <td class="{ROW_CLASS}" style="text-align: center;"><input type="radio" value="1" name="{FIELD_NAME}" {FIELD_DENY_CHECKED} /></td>
      <td class="{ROW_CLASS}" style="text-align: center;"><input type="radio" value="2" name="{FIELD_NAME}" {FIELD_DISALLOW_CHECKED} /></td>
      <td class="{ROW_CLASS}" style="text-align: center;"><input type="radio" value="3" name="{FIELD_NAME}" {FIELD_WIKIMODE_CHECKED} /></td>
      <td class="{ROW_CLASS}" style="text-align: center;"><input type="radio" value="4" name="{FIELD_NAME}" {FIELD_ALLOW_CHECKED} /></td>
    </tr>
<!-- ENDVAR acl_field_item -->
<!-- VAR acl_field_end -->
    <tr>
      <td colspan="6" class="row3">
        {lang:acl_lbl_help}
      </td>
    </tr>
  </table>
</div>
<!-- ENDVAR acl_field_end -->

