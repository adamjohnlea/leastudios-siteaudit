<?php
/**
 * URL create/edit form.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url|null $url_model
 * @var array<int, \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project> $projects
 * @var array<int, \LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency> $frequencies
 * @var array<int, \LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy> $strategies
 * @var string $post_url
 * @var string $list_url
 * @var string $create_action
 * @var string $update_action
 * @var string $run_audit_action
 */

defined( 'ABSPATH' ) || exit;

$is_edit     = $url_model instanceof \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
$form_action = $is_edit ? $update_action : $create_action;
$form_title  = $is_edit
	? __( 'Edit URL', 'leastudios-siteaudit' )
	: __( 'Add URL', 'leastudios-siteaudit' );

$url_value       = $is_edit ? $url_model->url()->value() : '';
$name_value      = $is_edit ? (string) ( $url_model->name() ?? '' ) : '';
$frequency_value = $is_edit ? $url_model->audit_frequency()->value : 'weekly';
$strategy_value  = $is_edit ? $url_model->audit_strategy()->value : 'both';
$project_value   = $is_edit ? $url_model->project_id() : null;
$enabled_value   = $is_edit ? $url_model->is_enabled() : true;
$alerts_enabled  = $is_edit ? $url_model->alerts_enabled() : false;
$threshold_score = $is_edit ? $url_model->alert_threshold_score() : null;
$threshold_drop  = $is_edit ? $url_model->alert_threshold_drop() : null;

\LEAStudios\SiteAudit\Admin\Notice_Service::render();
?>
<div class="wrap">
	<h1><?php echo esc_html( $form_title ); ?></h1>

	<form method="post" action="<?php echo esc_url( $post_url ); ?>">
		<?php wp_nonce_field( $form_action ); ?>
		<input type="hidden" name="action" value="<?php echo esc_attr( $form_action ); ?>" />
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="id" value="<?php echo esc_attr( (string) (int) $url_model->id() ); ?>" />
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="leastudios-siteaudit-url-url"><?php esc_html_e( 'URL', 'leastudios-siteaudit' ); ?></label>
					</th>
					<td>
						<?php if ( $is_edit ) : ?>
							<p><code><?php echo esc_html( $url_value ); ?></code></p>
							<p class="description"><?php esc_html_e( 'The URL itself cannot be changed once a row exists. Delete and re-add to point to a different address.', 'leastudios-siteaudit' ); ?></p>
						<?php else : ?>
							<input
								type="url"
								id="leastudios-siteaudit-url-url"
								name="url"
								value="<?php echo esc_attr( $url_value ); ?>"
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
							value="<?php echo esc_attr( $name_value ); ?>"
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
							<?php foreach ( $frequencies as $frequency_case ) : ?>
								<option value="<?php echo esc_attr( $frequency_case->value ); ?>" <?php selected( $frequency_case->value, $frequency_value ); ?>>
									<?php echo esc_html( $frequency_case->label() ); ?>
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
							<?php foreach ( $strategies as $strategy_case ) : ?>
								<option value="<?php echo esc_attr( $strategy_case->value ); ?>" <?php selected( $strategy_case->value, $strategy_value ); ?>>
									<?php echo esc_html( $strategy_case->label() ); ?>
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
							<?php foreach ( $projects as $project_option ) : ?>
								<?php $option_id = (int) $project_option->id(); ?>
								<option value="<?php echo esc_attr( (string) $option_id ); ?>" <?php selected( $option_id, $project_value ); ?>>
									<?php echo esc_html( $project_option->name()->value() ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<?php if ( $is_edit ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'leastudios-siteaudit' ); ?></th>
						<td>
							<input type="hidden" name="enabled" value="0" />
							<label>
								<input type="checkbox" name="enabled" value="1" <?php checked( $enabled_value ); ?> />
								<?php esc_html_e( 'Include in scheduled audits', 'leastudios-siteaudit' ); ?>
							</label>
						</td>
					</tr>
				<?php endif; ?>

				<tr>
					<th scope="row"><?php esc_html_e( 'Alerts', 'leastudios-siteaudit' ); ?></th>
					<td>
						<input type="hidden" name="alerts_enabled" value="0" />
						<label>
							<input type="checkbox" name="alerts_enabled" value="1" <?php checked( $alerts_enabled ); ?> />
							<?php esc_html_e( 'Enable threshold alert emails for this URL', 'leastudios-siteaudit' ); ?>
						</label>
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
							value="<?php echo esc_attr( null !== $threshold_score ? (string) $threshold_score : '' ); ?>"
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
							value="<?php echo esc_attr( null !== $threshold_drop ? (string) $threshold_drop : '' ); ?>"
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
			<?php submit_button( $is_edit ? __( 'Save Changes', 'leastudios-siteaudit' ) : __( 'Add URL', 'leastudios-siteaudit' ), 'primary', 'submit', false ); ?>
			<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Cancel', 'leastudios-siteaudit' ); ?></a>
		</p>
	</form>

	<?php if ( $is_edit ) : ?>
		<form method="post" action="<?php echo esc_url( $post_url ); ?>">
			<?php wp_nonce_field( $run_audit_action ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( $run_audit_action ); ?>" />
			<input type="hidden" name="id" value="<?php echo esc_attr( (string) (int) $url_model->id() ); ?>" />
			<p class="submit">
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Run audit now', 'leastudios-siteaudit' ); ?></button>
			</p>
		</form>
	<?php endif; ?>
</div>
