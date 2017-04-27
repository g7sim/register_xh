<h1><?=$this->text('mnu_user_admin')?></h1>
<div class="register_admin_main">
    <table>
        <tr id="register_user_template" style="display: none">
            <td><img src="<?=$this->deleteIcon()?>" alt="<?=$this->text('user_delete')?>" title="<?=$this->text('user_delete')?>" onclick="register.removeRow(this); return false"></td>
            <td><input type="text" value="" name="name[]"></td>
            <td><input type="text" value="<?=$this->defaultGroup()?>" name="accessgroups[]"></td>
            <td><?=$this->statusSelectActivated()?></td>
        </tr>
        <tr style="display: none">
            <td></td>
            <td><input type="text" value="" name="username[]"></td>
            <td><input type="text" value="" name="email[]"></td>
            <td>
                <button onclick="register.changePassword(this.nextElementSibling); return false"><?=$this->text('change_password')?></button>
                <input type="hidden" value="" name="password[]">
                <input type="hidden" value="" name="oldpassword[]">
            </td>
        </tr>
    </table>
    <div>
        <button onclick="register.addRow()"><?=$this->text('user_add')?></button>
        <input id="register_toggle_details" type="checkbox" onclick="register.toggleDetails()" style="padding-left: 1em">
        <label for="register_toggle_details"><?=$this->text('details')?></label>
        <?=$this->groupSelect()?>
    </div>
    <form id="register_user_form" method="post" action="<?=$this->actionUrl()?>">
        <input type="hidden" value="saveusers" name="action">
        <input type="hidden" value="plugin_main" name="admin">
        <table id="register_user_table">
            <tr>
                <th></th>
                <th onclick="register.sort(this, 'name')" style="cursor: pointer"><?=$this->text('name')?></th>
                <th onclick="register.sort(this, 'accessgroups')" style="cursor: pointer"><?=$this->text('accessgroups')?></th>
                <th onclick="register.sort(this, 'status')" style="cursor: pointer"><?=$this->text('status')?></th>
            </tr>
            <tr class="register_second_row">
                <th></th>
                <th onclick="register.sort(this, 'username')" style="cursor: pointer"><?=$this->text('username')?></th>
                <th onclick="register.sort(this, 'email')" style="cursor: pointer"><?=$this->text('email')?></th>
                <th><?=$this->text('password')?></th>
            </tr>
<?php foreach ($this->users as $i => $entry):?>
            <tr id="register_user_<?=$this->escape($i)?>">
                <td><img src="<?=$this->deleteIcon()?>" alt="<?=$this->text('user_delete')?>" title="<?=$this->text('user_delete')?>" onclick="register.removeRow(this); return false"></td>
                <td><input type="text" value="<?=$this->escape($entry['name'])?>" name="name[<?=$this->escape($i)?>]"></td>
                <td><input type="text" value="<?=$this->escape($this->groupStrings[$i])?>" name="accessgroups[<?=$this->escape($i)?>]"></td>
                <td><?=$this->escape($this->statusSelects[$i])?></td>
            </tr>
            <tr class="register_second_row">
                <td><a onclick="register.mailTo(this)"><img src="<?=$this->mailIcon()?>" alt="<?=$this->text('email')?>" title="<?=$this->text('email')?>"></a></td>
                <td><input type="text" value="<?=$this->escape($entry['username'])?>" name="username[<?=$this->escape($i)?>]"></td>
                <td><input type="text" value="<?=$this->escape($entry['email'])?>" name="email[<?=$this->escape($i)?>]"></td>
                <td>
                    <button onclick="register.changePassword(this.nextElementSibling); return false"><?=$this->text('change_password')?></button>
                    <input type="hidden" value="<?=$this->escape($entry['password'])?>" name="password[<?=$this->escape($i)?>]">
                    <input type="hidden" value="<?=$this->escape($entry['password'])?>" name="oldpassword[<?=$this->escape($i)?>]">
                </td>
            </tr>
<?php endforeach?>
        </table>
        <input class="submit" type="submit" value="<?=$this->saveLabel()?>" name="send">
    </form>
</div>
<script type="text/javascript">register.init()</script>
