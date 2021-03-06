<?php
require_once(__DIR__ . '/admin.php');

if (!array_key_exists('id', $_GET) || !$_GET['id']) {
	header("HTTP/1.0 400 User ID is not specified");
	?><h1>400 User ID is not specified</h1><?php
	exit;
}

$user_id = intval(trim($_GET['id']));

$user = User::getUser($user_id);
if (is_null($user)) {
	header("HTTP/1.0 404 User Not Found");
	?><h1>404 User Not Found</h1><?php
	exit;
}

if (array_key_exists("savefeatures", $_POST)) {
	$features_to_set = array();

	if (array_key_exists("feature", $_POST) && is_array($_POST['feature'])) {
		foreach (array_keys($_POST['feature']) as $featureid) {
			$feature = Feature::getByID($featureid);
			if (!is_null($feature) && !$feature->isRolledOutToAllUsers()) {
				$features_to_set[] = $feature;
			}
		}
	}

	$user->setFeatures($features_to_set);

	header('Location: ' . UserConfig::$USERSROOTURL . '/admin/user.php?id=' . $_GET['id'] . '#featuressaved');
	exit;
}

if (array_key_exists("activate", $_POST)) {
	$user->setStatus(true);
	$user->save();

	header('Location: ' . UserConfig::$USERSROOTURL . '/admin/user.php?id=' . $_GET['id'] . '#activated');
	exit;
}

if (array_key_exists("deactivate", $_POST)) {
	$user->setStatus(false);
	$user->save();

	header('Location: ' . UserConfig::$USERSROOTURL . '/admin/user.php?id=' . $_GET['id'] . '#deactivated');
	exit;
}

$ADMIN_SECTION = 'registrations';
$BREADCRUMB_EXTRA = $user->getName();
require_once(__DIR__ . '/header.php');
?>
<div class="span9">

	<form action="" method="POST">
		<h2>
			<?php echo UserTools::escape($user->getName()); ?>
			<span class="startupapi-admin-user-id">(ID: <?php echo $user->getID() ?>)</span>
			<?php if ($user->isAdmin()) { ?>
				<span class="badge badge-important">system admin</span>
			<?php } ?>
			<div class="pull-right">
				<?php
				if ($user->isDisabled()) {
					?>
					<b style="color: red">Deactivated</b>
					<input class="btn btn-success" type="submit" name="activate" value="Activate"
						   style="font: small"
						   onclick="return confirm('Are you sure you want to activate this user?')"/>
						   <?php
					   } else {
						   if (!$user->isTheSameAs($current_user)) {
							   ?>
						<form name="imp" action="" method="POST"><input class="btn btn-inverse" type="submit"
																		value="Impersonate"
																		style="font: small"/><input type="hidden"
																		name="impersonate"
																		value="<?php echo $user->getID() ?>"/>
																		<?php UserTools::renderCSRFNonce(); ?>
						</form>
						<?php
					}
					?>
					<input type="submit" class="btn btn-danger" name="deactivate" value="Deactivate"
						   style="font: small"
						   onclick="return confirm('Are you sure you want to disable access for this user?')"/>
						   <?php
					   }
					   UserTools::renderCSRFNonce();
					   ?>
			</div>
		</h2>
	</form>

	<p>
		<?php
		$email = $user->getEmail();

		if ($email) {
			?>
			<a href="mailto:<?php echo urlencode(UserTools::escape($email)) ?>" target="_blank">
				<i class="icon-envelope"></i> <?php echo UserTools::escape($email) ?>
			</a>
			<?php
		}
		?>
	</p>

	<p>
		<?php
		$regtime = $user->getRegTime();
		$ago = intval(floor((time() - $regtime) / 86400));
		?>
		<b>Registered:</b> <?php echo date('M j, Y h:iA', $regtime) ?>
		<span class="badge<?php if ($ago <= 5) { ?> badge-success<?php } ?>"><?php echo $ago ?></span>
		day<?php echo $ago != 1 ? 's' : '' ?> ago
	</p>

	<p>
		<b>Activity points:</b> <span class="badge"><?php echo $user->getPoints(); ?></span> <a
			class="btn btn-small" href="activity.php?userid=<?php echo $user->getID() ?>"><i
				class="icon-signal"></i> See activity</a>
	</p>

	<h3>Gamification</h3>
	<?php
	$adminBadgeSize = 57;

	$user_badges = $user->getBadges();
	$available_badges = Badge::getAvailableBadges();

	foreach ($available_badges as $badge) {
		?><a href="<?php echo UserConfig::$USERSROOTURL ?>/admin/badge.php?id=<?php echo $badge->getID() ?>"><?php
	if (array_key_exists($badge->getID(), $user_badges)) {
		$badge_level = $user_badges[$badge->getID()][1];
			?><img
					style="margin-right: 0.5em"
					src="<?php echo $badge->getImageURL($adminBadgeSize, $badge_level) ?>"
					title="<?php echo $badge->getTitle() ?>"
					width="<?php echo $adminBadgeSize ?>"
					height="<?php echo $adminBadgeSize ?>"/>
					<?php
				} else {
					?><img style="margin-right: 0.5em"
					 src="<?php echo $badge->getPlaceholderImageURL($adminBadgeSize) ?>"
					 title="Hint: <?php echo $badge->getHint() ?>"
					 width="<?php echo $adminBadgeSize ?>"
					 height="<?php echo $adminBadgeSize ?>"/>
					 <?php
				 }
				 ?></a><?php
	}
			 ?>

	<h3>Authentication Credentials</h3>
	<ul><?php
	foreach (UserConfig::$authentication_modules as $module) {
		$creds = $module->getUserCredentials($user);

		if (!is_null($creds)) {
					 ?>
				<li><b><?php echo $module->getID() ?>: </b><?php echo $creds->getHTML() ?></li>
				<?php
			}
		}
		?>
	</ul>

	<h3>Accounts:</h3>
	<table class="table">
		<?php
		$accounts_and_roles = $user->getAccountsAndRoles();

		foreach ($accounts_and_roles as $account_and_role) {
			$user_account = $account_and_role[0];
			$role = $account_and_role[1];
			?>
			<tr>
				<td>
					<a href="<?php echo UserConfig::$USERSROOTURL ?>/admin/account.php?id=<?php echo $user_account->getID() ?>">
						<?php echo UserTools::escape($user_account->getName()); ?>
					</a>
				</td>
				<td>
					<?php if ($role == Account::ROLE_ADMIN) { ?>
						<span class="badge badge-important">admin</span>
					<?php } ?>
				</td>
				<td>
					<?php
					$plan = $user_account->getPlan(); // can be FALSE
					if ($plan) {
						?>
						<a class="badge badge-info"
						   href="<?php echo UserConfig::$USERSROOTURL ?>/admin/plan.php?slug=<?php echo UserTools::escape($plan->getSlug()); ?>">
							<i class="icon-briefcase icon-white"></i>
							<?php echo UserTools::escape($plan->getName()); ?>
						</a>
						<?php
					} else {
						?>
						<span class="badge badge-important">NO PLAN</span>
						<?php
					}
					?>
				</td>
			</tr>
		<?php } ?>
	</table>

	<h3>Source of registration</h3>

	<p>Referrer:
		<?php
		$referer = $user->getReferer();

		if (is_null($referer)) {
			?>
			<i>unknown</i>
			<?php
		} else {
			?>
			<a target="_blank" href="<?php echo UserTools::escape($referer) ?>">
				<?php echo UserTools::escape($referer) ?>
			</a>
			<?php
		}
		?>
	</p>
	<?php
	$campaign = $user->getCampaign();
	if (count($campaign) > 0) {
		?>
		<h4>Campaign codes</h4>
		<?php
	}

	if (array_key_exists('cmp_name', $campaign)) {
		?>
		<p>Name: <b><?php echo UserTools::escape($campaign['cmp_name']) ?></b></p>
		<?php
	}
	if (array_key_exists('cmp_source', $campaign)) {
		?>
		<p>Source: <b><?php echo UserTools::escape($campaign['cmp_source']) ?></b></p>
		<?php
	}
	if (array_key_exists('cmp_medium', $campaign)) {
		?>
		<p>Medium: <b><?php echo UserTools::escape($campaign['cmp_medium']) ?></b></p>
		<?php
	}
	if (array_key_exists('cmp_keywords', $campaign)) {
		?>
		<p>Keywords: <b><?php echo UserTools::escape($campaign['cmp_keywords']) ?></b></p>
		<?php
	}
	if (array_key_exists('cmp_content', $campaign)) {
		?>
		<p>Content: <b><?php echo UserTools::escape($campaign['cmp_content']) ?></b></p>
		<?php
	}

	$features = Feature::getAll();
	$accounts = $user->getAccounts();
	if (count($features) > 0) {
		$has_features_to_save = false;
		?>
		<h3>Features</h3>
		<p>You can enable or remove particular features for this user</p>
		<p>Keep in mind that if set for a user, it overrides account feature settings so most of the times, you might
			want to control features on
			account level instead of here.</p>
		<form class="form" action="" method="POST">
			<?php
			foreach ($features as $id => $feature) {
				$disable_editing = $feature->isRolledOutToAllUsers() || !$feature->isEnabled() || $feature->isShutDown();
				$is_checked = $feature->isRolledOutToAllUsers() || $feature->isEnabledForUser($user, true);
				?>
				<div
				<?php
				if ($feature->isShutDown()) {
					?>
						style="color: red; text-decoration: line-through"
						title="Feature is shut down due to emergency"
						<?php
					} else if (!$feature->isEnabled()) {
						?>
						style="color: grey; text-decoration: line-through"
						title="Feature is disabled"
						<?php
					}
					?>
					>
					<label class="checkbox">
						<input id="feature_<?php echo UserTools::escape($feature->getID()) ?>"
							   type="checkbox"
							   name="feature[<?php echo UserTools::escape($feature->getID()) ?>]"
							   <?php
							   if ($is_checked) {
								   ?>
								   checked="true"
								   <?php
							   }

							   if ($disable_editing) {
								   ?>
								   disabled="disabled"
								   <?php
							   } else {
								   $has_features_to_save = true;
							   }
							   ?>
							   >
							   <?php
							   if ($disable_editing && $feature->isEnabledForUser($user, true)) {
								   ?>
							<input type="hidden"
								   name="feature[<?php echo UserTools::escape($feature->getID()) ?>]"
								   value="true"/>
								   <?php
							   }
							   ?>
							   <?php
							   echo UserTools::escape($feature->getName())
							   ?>
							   <?php
							   $enabled_for_accounts = array();
							   foreach ($accounts as $account) {
								   if ($feature->isEnabledForAccount($account)) {
									   $enabled_for_accounts[] = $account;
								   }
							   }

							   if ($feature->isEnabled()) {
								   if ($feature->isRolledOutToAllUsers()) {
									   ?>
								<br/>
								(Rolled out to all users)
								<?php
							} else if (count($enabled_for_accounts) > 0) {
								?>
								<br/>
								(Enabled in accounts:
								<?php
								$first = true;
								foreach ($enabled_for_accounts as $feature_account) {
									if (!$first) {
										?>
										,
										<?php
									}
									?>
									<a href="<?php echo UserConfig::$USERSROOTURL ?>/admin/account.php?id=<?php echo $feature_account->getID() ?>">
										<?php echo $feature_account->getName() ?>
									</a>
									<?php
									$first = false;
								}
								?>
								)
								<?php
							}
						}
						?>
					</label>
				</div>
			<?php } ?>
			<input class="btn"
				   type="submit"
				   name="savefeatures"
				   value="update features"
				   <?php
				   if (!$has_features_to_save) {
					   ?>
					   disabled="disabled"
					   <?php
				   }
				   ?>
				   >
				   <?php
				   UserTools::renderCSRFNonce();
				   ?>
		</form>
		<?php
	}
	?>

</div>
<?php
require_once(__DIR__ . '/footer.php');
