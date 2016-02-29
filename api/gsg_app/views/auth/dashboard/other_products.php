		<div id="past-test"><p>Select any number of the following products and click &quot;Request More Information.&quot;  An <?php $this->config->item('cust_serv_company')?> representative will follow-up with you and answer any questions you have.</p>
			<form action="auth/product_info_request" id="benchmark-form" method="post">
                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
				<?php
				foreach($products as $a):
					?><p><?php
						echo form_checkbox('products[]', $a->productCode());
						echo $a->description();
					?></p><?php
				endforeach;
				?><p><?php
				echo form_label('Comments or Questions');
				?></p><?php
				?><p><?php
				echo form_textarea(['name'=>'comments', 'rows'=>'3', 'cols'=>'30']);
				?></p><?php
				?><p><?php
				echo form_submit('submit_sections','Request More Information', 'class="button"') ?>
				</p>
			</form>
		<?php
		if(isset($inner_html)) echo $inner_html; ?>
		</div>
