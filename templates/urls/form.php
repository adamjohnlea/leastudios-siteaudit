<?php
/**
 * URL create/edit form.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url|null $leastudios_siteaudit_url_model
 * @var array<int, \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project> $leastudios_siteaudit_projects
 * @var array<int, \LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency> $leastudios_siteaudit_frequencies
 * @var array<int, \LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy> $leastudios_siteaudit_strategies
 * @var string $leastudios_siteaudit_post_url
 * @var string $leastudios_siteaudit_list_url
 * @var string $leastudios_siteaudit_create_action
 * @var string $leastudios_siteaudit_update_action
 * @var string $leastudios_siteaudit_run_audit_action
 */

defined( 'ABSPATH' ) || exit;

$leastudios_siteaudit_is_edit     = $leastudios_siteaudit_url_model instanceof \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
$leastudios_siteaudit_form_action = $leastudios_siteaudit_is_edit ? $leastudios_siteaudit_update_action : $leastudios_siteaudit_create_action;
$leastudios_siteaudit_form_title  = $leastudios_siteaudit_is_edit
	? __( 'Edit URL', 'leastudios-siteaudit' )
	: __( 'Add URL', 'leastudios-siteaudit' );

$leastudios_siteaudit_url_value       = $leastudios_siteaudit_is_edit ? $leastudios_siteaudit_url_model->url()->value() : '';
$leastudios_siteaudit_name_value      = $leastudios_siteaudit_is_edit ? (string) ( $leastudios_siteaudit_url_model->name() ?? '' ) : '';
$leastudios_siteaudit_frequency_value = $leastudios_siteaudit_is_edit ? $leastudios_siteaudit_url_model->audit_frequency()->value : 'weekly';
$leastudios_siteaudit_strategy_value  = $leastudios_siteaudit_is_edit ? $leastudios_siteaudit_url_model->audit_strategy()->value : 'both';
$leastudios_siteaudit_project_value   = $leastudios_siteaudit_is_edit ? $leastudios_siteaudit_url_model->project_id() : null;
$leastudios_siteaudit_enabled_value   = $leastudios_siteaudit_is_edit ? $leastudios_siteaudit_url_model->is_enabled() : true;
$leastudios_siteaudit_alerts_enabled  = $leastudios_siteaudit_is_edit ? $leastudios_siteaudit_url_model->alerts_enabled() : false;
$leastudios_siteaudit_threshold_score = $leastudios_siteaudit_is_edit ? $leastudios_siteaudit_url_model->alert_threshold_score() : null;
$leastudios_siteaudit_threshold_drop  = $leastudios_siteaudit_is_edit ? $leastudios_siteaudit_url_model->alert_threshold_drop() : null;

\LEAStudios\SiteAudit\Admin\Notice_Service::render();
?>
<div class="wrap">
	<h1><?php echo esc_html( $leastudios_siteaudit_form_title ); ?></h1>

	<form method="post" action="<?php echo esc_url( $leastudios_siteaudit_post_url ); ?>">
		<?php wp_nonce_field( $leastudios_siteaudit_form_action ); ?>
		<input type="hidden" name="action" value="<?php echo esc_attr( $leastudios_siteaudit_form_action ); ?>" />
		<?php if ( $leastudios_siteaudit_is_edit ) : ?>
			<input type="hidden" name="id" value="<?php echo esc_attr( (string) (int) $leastudios_siteaudit_url_model->id() ); ?>" />
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="leastudios-siteaudit-url-url"><?php esc_html_e( 'URL', 'leastudios-siteaudit' ); ?></label>
					</th>
					<td>
						<?php if ( $leastudios_siteaudit_is_edit ) : ?>
							<p><code><?php echo esc_html( $leastudios_siteaudit_url_value ); ?></code></p>
							<p class="description"><?php esc_html_e( 'The URL itself cannot be changed once a row exists. Delete and re-add to point to a different address.', 'leastudios-siteaudit' ); ?></p>
						<?php else : ?>
							<input
								type="url"
								id="leastudios-siteaudit-url-url"
								name="url"
								value="<?php echo esc_attr( $leastudios_siteaudit_url_value ); ?>"
								class="regular-text"
								placeholder="https://example.com"
								required
							/>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="leastudios-siteaudit-url-name"><?php esc_html_e( 'Name', 'leastudios-siteaudit' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							id="leastudios-siteaudit-url-name"
							name="name"
							value="<?php echo esc_attr( $leastudios_siteaudit_name_value ); ?>"
							class="regular-text"
							required
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="leastudios-siteaudit-url-frequency"><?php esc_html_e( 'Audit frequency', 'leastudios-siteaudit' ); ?></label>
					</th>
					<td>
						<select id="leastudios-siteaudit-url-frequency" name="frequency">
							<?php foreach ( $leastudios_siteaudit_frequencies as $leastudios_siteaudit_frequency_case ) : ?>
								<option value="<?php echo esc_attr( $leastudios_siteaudit_frequency_case->value ); ?>" <?php selected( $leastudios_siteaudit_frequency_case->value, $leastudios_siteaudit_frequency_value ); ?>>
									<?php echo esc_html( $leastudios_siteaudit_frequency_case->label() ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="leastudios-siteaudit-url-strategy"><?php esc_html_e( 'Audit strategy', 'leastudios-siteaudit' ); ?></label>
					</th>
					<td>
						<select id="leastudios-siteaudit-url-strategy" name="strategy">
							<?php foreach ( $leastudios_siteaudit_strategies as $leastudios_siteaudit_strategy_case ) : ?>
								<option value="<?php echo esc_attr( $leastudios_siteaudit_strategy_case->value ); ?>" <?php selected( $leastudios_siteaudit_strategy_case->value, $leastudios_siteaudit_strategy_value ); ?>>
									<?php echo esc_html( $leastudios_siteaudit_strategy_case->label() ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="leastudios-siteaudit-url-project"><?php esc_html_e( 'Project', 'leastudios-siteaudit' ); ?></label>
					</th>
					<td>
						<select id="leastudios-siteaudit-url-project" name="project_id">
							<option value=""><?php esc_html_e( '— None —', 'leastudios-siteaudit' ); ?></option>
							<?php foreach ( $leastudios_siteaudit_projects as $leastudios_siteaudit_project_option ) : ?>
								<?php $leastudios_siteaudit_option_id = (int) $leastudios_siteaudit_project_option->id(); ?>
								<option value="<?php echo esc_attr( (string) $leastudios_siteaudit_option_id ); ?>" <?php selected( $leastudios_siteaudit_option_id, $leastudios_siteaudit_project_value ); ?>>
									<?php echo esc_html( $leastudios_siteaudit_project_option->name()->value() ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<?php if ( $leastudios_siteaudit_is_edit ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'leastudios-siteaudit' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php esc_html_e( 'Status', 'leastudios-siteaudit' ); ?></span></legend>
								<input type="hidden" name="enabled" value="0" />
								<label>
									<input type="checkbox" name="enabled" value="1" <?php checked( $leastudios_siteaudit_enabled_value ); ?> />
									<?php esc_html_e( 'Include in scheduled audits', 'leastudios-siteaudit' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
				<?php endif; ?>

				<tr>
					<th scope="row"><?php esc_html_e( 'Alerts', 'leastudios-siteaudit' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php esc_html_e( 'Alerts', 'leastudios-siteaudit' ); ?></span></legend>
							<input type="hidden" name="alerts_enabled" value="0" />
							<label>
								<input type="checkbox" name="alerts_enabled" value="1" <?php checked( $leastudios_siteaudit_alerts_enabled ); ?> />
								<?php esc_html_e( 'Enable threshold alert emails for this URL', 'leastudios-siteaudit' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="leastudios-siteaudit-url-threshold-score"><?php esc_html_e( 'Alert when score below', 'leastudios-siteaudit' ); ?></label>
					</th>
					<td>
						<input
							type="number"
							id="leastudios-siteaudit-url-threshold-score"
							name="alert_threshold_score"
							value="<?php echo esc_attr( null !== $leastudios_siteaudit_threshold_score ? (string) $leastudios_siteaudit_threshold_score : '' ); ?>"
							min="0"
							max="100"
							class="small-text"
						/>
						<p class="description"><?php esc_html_e( 'Leave blank to disable. Range 0–100.', 'leastudios-siteaudit' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="leastudios-siteaudit-url-threshold-drop"><?php esc_html_e( 'Alert on score drop', 'leastudios-siteaudit' ); ?></label>
					</th>
					<td>
						<input
							type="number"
							id="leastudios-siteaudit-url-threshold-drop"
							name="alert_threshold_drop"
							value="<?php echo esc_attr( null !== $leastudios_siteaudit_threshold_drop ? (string) $leastudios_siteaudit_threshold_drop : '' ); ?>"
							min="1"
							max="100"
							class="small-text"
						/>
						<p class="description"><?php esc_html_e( 'Leave blank to disable. Range 1–100 points.', 'leastudios-siteaudit' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<?php submit_button( $leastudios_siteaudit_is_edit ? __( 'Save Changes', 'leastudios-siteaudit' ) : __( 'Add URL', 'leastudios-siteaudit' ), 'primary', 'submit', false ); ?>
			<a href="<?php echo esc_url( $leastudios_siteaudit_list_url ); ?>" class="button"><?php esc_html_e( 'Cancel', 'leastudios-siteaudit' ); ?></a>
		</p>
	</form>

	<?php if ( $leastudios_siteaudit_is_edit ) : ?>
		<form method="post" action="<?php echo esc_url( $leastudios_siteaudit_post_url ); ?>">
			<?php wp_nonce_field( $leastudios_siteaudit_run_audit_action ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( $leastudios_siteaudit_run_audit_action ); ?>" />
			<input type="hidden" name="id" value="<?php echo esc_attr( (string) (int) $leastudios_siteaudit_url_model->id() ); ?>" />
			<p class="submit">
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Run audit now', 'leastudios-siteaudit' ); ?></button>
			</p>
		</form>
	<?php endif; ?>
</div>
