<?php if (isset($roles)): ?>
    <div class="project_save_container">
        <button class="button button-silver" onclick="$('new_project_role').toggle();if ($('new_project_role').visible()) { $('add_new_role_input').focus(); }"><?php echo __('Create project role'); ?></button>
        <a class="dropper button button-silver"><?php echo __('More actions'); ?></a>
        <ul class="simple_list rounded_box white shadowed more_actions_dropdown popup_box">
            <li><?php echo javascript_link_tag(__('Show advanced permissions'), array('onclick' => "$('project_settings_roles').toggle();$('project_settings_advanced_permissions').toggle();")); ?></li>
        </ul>
    </div>
    <div id="project_settings_roles" style="position: relative;">
        <div class="content faded_out">
            <p><?php echo __("Roles are applied when assigning users or teams to a project, granting them access to specific parts of the project or giving users access to update and edit information. Updating permissions in this list will add or remove permissions for all users and / or team members with that role, on all assigned projects. Removing a role removes all permissions granted by that role for all users and teams. Read more about roles and permissions in the %online_documentation", array('%online_documentation' => link_tag('http://issues.thebuggenie.com/wiki/TheBugGenie:RolesAndPermissions', '<b>'.__('online documentation').'</b>'))); ?></p>
        </div>
        <h4><?php echo __('Globally available roles'); ?></h4>
        <ul id="global_roles_list" class="simple_list" style="width: 788px;">
            <?php foreach ($roles as $role): ?>
                <?php include_template('configuration/role', array('role' => $role)); ?>
            <?php endforeach; ?>
            <li class="faded_out" id="global_roles_no_roles"<?php if (count($roles)): ?> style="display: none;"<?php endif; ?>><?php echo __('There are no globally available roles'); ?></li>
        </ul>
        <h4><?php echo __('Project-specific roles'); ?></h4>
        <div class="rounded_box white shadowed" id="new_project_role" style="display: none; position: absolute; right: 0; z-index: 10">
            <form id="new_project_role_form" method="post" action="<?php echo make_url('project_create_role', array('project_key' => $project->getKey())); ?>" onsubmit="TBG.Project.Roles.add('<?php echo make_url('project_create_role', array('project_key' => $project->getKey())); ?>'); return false;" accept-charset="<?php echo TBGContext::getI18n()->getCharset(); ?>">
                <label for="new_project_role_name"><?php echo __('Role name'); ?></label>
                <input type="text" style="width: 300px;" name="role_name" id="add_new_role_input">
                <?php echo image_tag('spinning_16.gif', array('style' => 'display: none; float: right; margin: 2px 5px 2px 5px;', 'id' => 'new_project_role_form_indicator')); ?>
                <input type="submit" value="<?php echo __('Create role'); ?>" class="button button-silver" style="float: right; margin: 1px 1px 1px 5px;">
            </form>
        </div>
        <ul id="project_roles_list" class="simple_list" style="width: 788px;">
            <?php foreach ($project_roles as $role): ?>
                <?php include_template('configuration/role', array('role' => $role)); ?>
            <?php endforeach; ?>
            <li class="faded_out no_roles" id="project_roles_no_roles"<?php if (count($project_roles)): ?> style="display: none;"<?php endif; ?>><?php echo __('There are no project-specific roles available'); ?></li>
        </ul>
    </div>
<?php endif; ?>
<div class="permission_list" id="project_settings_advanced_permissions"<?php if (isset($roles)): ?> style="display: none;"<?php endif; ?>>
    <?php if (isset($roles)): ?>
        <div class="project_save_container">
            <div class="button button-silver" onclick="$('project_settings_roles').toggle();$('project_settings_advanced_permissions').toggle();"><?php echo __('Show roles'); ?></div>
        </div>
    <?php endif; ?>
    <h3>
        <?php echo __('Editing advanced project permissions'); ?>
    </h3>
    <div class="content faded_out">
        <p><?php echo __('These permissions directly control what you can do, and which pages you can access in The Bug Genie - on a project-specific basis. Some of these permissions are also available as site-wide permissions in the %permissions_configuration page. You may want to use roles and assignments instead of applying these permissions directly.', array('%permissions_configuration' => '<b>'.link_tag(make_url('configure_permissions'), __('permissions configuration')).'</b>')); ?></p>
    </div>
    <ul id="project_permission_details_<?php echo $project->getID(); ?>" style="margin-top: 10px;">
        <?php include_template('configuration/permissionsblock', array('base_id' => 0 . 'project_' . $project->getID() . '_project_permissions', 'permissions_list' => TBGContext::getAvailablePermissions('project'), 'mode' => 'general', 'target_id' => $project->getID(), 'module' => 'core', 'access_level' => $access_level)); ?>
        <?php include_template('configuration/permissionsblock', array('base_id' => 0 . 'project_' . $project->getID() . '_page_permissions', 'permissions_list' => TBGContext::getAvailablePermissions('project_pages'), 'mode' => 'project_pages', 'target_id' => $project->getID(), 'module' => 'core', 'access_level' => $access_level)); ?>
        <?php include_template('configuration/permissionsblock', array('base_id' => 0 . 'project_' . $project->getID() . '_issue_permissions', 'permissions_list' => TBGContext::getAvailablePermissions('issues'), 'mode' => 'general', 'target_id' => $project->getID(), 'module' => 'core', 'access_level' => $access_level)); ?>
    </ul>
</div>
