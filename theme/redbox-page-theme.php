<?php 
/*
Template Name: Page RedBox
*/
get_header(); ?>

<style>

.mt-toggle .mt-toggle-title:hover, 
.mt-toggle.active .mt-toggle-title{
background-color:#D31D1D !important;
}

.mt-toggle .mt-toggle-title:hover .ui-icon,
.mt-toggle.active .mt-toggle-title .ui-icon{
background-color:#9E0F0F !important;
}

.juiz_sps_links{
display:none;
}

</style>
<div class="content-area">
	<div class="page-title clearfix">
		<div class="container">
			<div class="twelve columns clearfix">
				<h1><?php the_title(); ?></h1>
			</div>
		</div>
	</div>

	<div class="content clearfix">
		<div class="container">
			<div class="twelve columns">

			<?php if (have_posts()) : while (have_posts()) : the_post(); ?>

				<?php
					$thumb = get_post_thumbnail_id();
					$img_url = wp_get_attachment_url( $thumb,'full' ); //get full URL to image (use "large" or "medium" if the images too big)
					$image = aq_resize( $img_url, 1110, '', true ); //resize & crop the image
				?>
				<?php if($image) : ?>
					<!--<img src="<?php echo $image ?>" class="featured-image-page" />-->
				<?php endif; ?>

				<?php the_content(); ?>
				<?php comments_template(); ?>
				<?php wp_link_pages( array( 'before' => '<div class="page-link">' . __( 'Pages:', 'mthemes' ), 'after' => '</div>' ) ); ?>

			<?php endwhile; endif; ?>

			</div><!-- / .twelve columns -->
		</div><!-- / .container -->
	</div><!-- / .content -->
</div><!-- / .content-area -->
<?php
// for page title
$modify_page_title = get_post_meta( get_the_ID(), 'mt_modify_default_pagetitle', true);
if($modify_page_title == 'yes') { echo get_template_part( 'includes/page-title-options' ); }
?>

<?php get_footer(); ?>
