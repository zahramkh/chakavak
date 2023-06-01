<?php
/**
 * Developer : MahdiY
 * Web Site  : MahdiY.IR
 * E-Mail    : M@hdiY.IR
 */

defined( 'ABSPATH' ) || exit;

use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

/**
 * Class Persian_Woocommerce_Date
 *
 * @author  mahdiy
 * @package https://wordpress.org/plugins/persian-date/
 */
class Persian_Woocommerce_Date {

	public function __construct() {
		global $wp_version;

		if ( PW()->get_options( 'enable_jalali_datepicker', 'no' ) != 'yes' ) {
			return false;
		}

		add_action( 'woocommerce_process_shop_order_meta', [ $this, 'process_shop_order_meta' ], 100, 1 );
		add_action( 'woocommerce_process_product_meta', [ $this, 'process_product_meta' ], 100, 1 );
		add_action( 'woocommerce_ajax_save_product_variations', [ $this, 'ajax_save_product_variations' ], 100, 1 );
		add_action( 'woocommerce_process_shop_coupon_meta', [ $this, 'process_shop_coupon_meta' ], 100, 1 );

		add_filter( 'pre_get_posts', [ $this, 'pre_get_posts' ] );
		add_action( 'restrict_manage_posts', [ $this, 'restrict_manage_posts' ] );

		if ( version_compare( $wp_version, '5.3', '<' ) ) {
			add_filter( 'date_i18n', [ $this, 'date_i18n' ], 100, 4 );
		}

		add_filter( 'wp_date', [ $this, 'wp_date' ], 100, 4 );
	}

	public function process_shop_order_meta( $order_id ) {

		if ( ! isset( $_POST['order_date'] ) ) {
			return false;
		}

		/** @var WC_Order $order */
		$order = wc_get_order( $order_id );

		$hour   = str_pad( intval( $_POST['order_date_hour'] ), 2, 0, STR_PAD_LEFT );
		$minute = str_pad( intval( $_POST['order_date_minute'] ), 2, 0, STR_PAD_LEFT );
		$second = str_pad( intval( $_POST['order_date_second'] ), 2, 0, STR_PAD_LEFT );

		$timestamp = wc_clean( $_POST['order_date'] ) . " {$hour}:{$minute}:{$second}";

		$jDate = Jalalian::fromFormat( 'Y-m-d H:i:s', self::en( $timestamp ) );

		// Update date.
		if ( empty( $_POST['order_date'] ) ) {
			$date = time();
		} else {
			$date = gmdate( 'Y-m-d H:i:s', $jDate->toCarbon()->timestamp );
		}

		$props['date_created'] = $date;

		// Save order data.
		$order->set_props( $props );
		$order->save();
	}

	public function process_product_meta( $product_id ) {

		// Handle dates.
		$props = [];

		// Force date from to beginning of day.
		if ( isset( $_POST['_sale_price_dates_from'] ) ) {
			$date_on_sale_from = wc_clean( wp_unslash( $_POST['_sale_price_dates_from'] ) );

			if ( ! empty( $date_on_sale_from ) ) {
				$jDate = Jalalian::fromFormat( 'Y-m-d', self::en( $date_on_sale_from ) );

				$props['date_on_sale_from'] = date( 'Y-m-d 00:00:00', $jDate->toCarbon()->timestamp );
			}
		}

		// Force date to to the end of the day.
		if ( isset( $_POST['_sale_price_dates_to'] ) ) {
			$date_on_sale_to = wc_clean( wp_unslash( $_POST['_sale_price_dates_to'] ) );

			if ( ! empty( $date_on_sale_to ) ) {
				$jDate = Jalalian::fromFormat( 'Y-m-d', self::en( $date_on_sale_to ) );

				$props['date_on_sale_to'] = date( 'Y-m-d 23:59:59', $jDate->toCarbon()->timestamp );
			}
		}

		if ( ! count( $props ) ) {
			return false;
		}

		/** @var WC_Product $product */
		$product = wc_get_product( $product_id );

		$product->set_props( $props );

		$product->save();
	}

	public static function ajax_save_product_variations( $product_id ) {

		if ( isset( $_POST['variable_post_id'] ) ) {

			$parent = wc_get_product( $product_id );

			$max_loop   = max( array_keys( wp_unslash( $_POST['variable_post_id'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$data_store = $parent->get_data_store();
			$data_store->sort_all_product_variations( $parent->get_id() );

			for ( $i = 0; $i <= $max_loop; $i ++ ) {

				if ( ! isset( $_POST['variable_post_id'][ $i ] ) ) {
					continue;
				}

				$variation_id = absint( $_POST['variable_post_id'][ $i ] );
				$variation    = wc_get_product_object( 'variation', $variation_id );

				// Handle dates.
				$props = [];

				// Force date from to beginning of day.
				if ( isset( $_POST['variable_sale_price_dates_from'][ $i ] ) ) {
					$date_on_sale_from = wc_clean( wp_unslash( $_POST['variable_sale_price_dates_from'][ $i ] ) );

					if ( ! empty( $date_on_sale_from ) ) {
						$jDate = Jalalian::fromFormat( 'Y-m-d', self::en( $date_on_sale_from ) );

						$props['date_on_sale_from'] = date( 'Y-m-d 00:00:00', $jDate->toCarbon()->timestamp );
					}
				}

				// Force date to to the end of the day.
				if ( isset( $_POST['variable_sale_price_dates_to'][ $i ] ) ) {
					$date_on_sale_to = wc_clean( wp_unslash( $_POST['variable_sale_price_dates_to'][ $i ] ) );

					if ( ! empty( $date_on_sale_to ) ) {
						$jDate = Jalalian::fromFormat( 'Y-m-d', self::en( $date_on_sale_to ) );

						$props['date_on_sale_to'] = date( 'Y-m-d 23:59:59', $jDate->toCarbon()->timestamp );
					}
				}

				if ( ! count( $props ) ) {
					continue;
				}

				$variation->set_props( $props );

				$variation->save();
			}
		}

	}

	public function process_shop_coupon_meta( $coupon_id ) {

		if ( ! isset( $_POST['expiry_date'] ) ) {
			return false;
		}

		$coupon = new WC_Coupon( $coupon_id );

		$expiry_date = wc_clean( $_POST['expiry_date'] );

		if ( ! empty( $expiry_date ) ) {
			$jDate = Jalalian::fromFormat( 'Y-m-d', self::en( $expiry_date ) );

			$expiry_date = $jDate->toCarbon()->format( 'Y-m-d' );
		}

		$coupon->set_props( [
			'date_expires' => $expiry_date,
		] );

		$coupon->save();
	}

	public function pre_get_posts( WP_Query $query ): WP_Query {

		$year_month = intval( $_GET['pwp_m'] ?? 0 );

		if ( empty( $year_month ) || ! is_numeric( $year_month ) ) {
			return $query;
		}

		$year  = (int) substr( $year_month, 0, 4 );
		$month = (int) substr( $year_month, 4, 2 );

		if ( $month < 0 || $month > 12 ) {
			return $query;
		}

		$date                                     = new Morilog\Jalali\Jalalian( $year, $month, 1 );
		$query->query_vars['date_query']['after'] = $date->toCarbon()->format( 'Y-m-d' );

		$date                                      = new Morilog\Jalali\Jalalian( $year, $month, $date->getMonthDays() );
		$query->query_vars['date_query']['before'] = $date->toCarbon()->format( 'Y-m-d' );

		$query->query_vars['date_query']['inclusive'] = true;

		return $query;
	}

	public function restrict_manage_posts() {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$order_dates = $wpdb->get_results(
			sprintf( "
				SELECT date( post_date_gmt ) AS date, count(*) as count

				FROM `%sposts`
				
				WHERE post_status != 'trash'
				and post_type = 'shop_order'
				and date( post_date_gmt ) != '0000-00-00' 
				and year( post_date_gmt ) > '2010'

				GROUP BY date( post_date_gmt )
				ORDER BY post_date_gmt DESC;
			", $wpdb->prefix )
		);

		$dates = [];

		foreach ( $order_dates as $order_date ) {

			$date = call_user_func_array( [
				CalendarUtils::class,
				'toJalali',
			], explode( '-', $order_date->date ) );

			if ( ! isset( $dates[ $date[0] ][ $date[1] ] ) ) {
				$dates[ $date[0] ][ $date[1] ] = 0;
			}

			$dates[ $date[0] ][ $date[1] ] += $order_date->count;
		}

		$month_names = [
			1  => 'فروردین',
			2  => 'اردیبهشت',
			3  => 'خرداد',
			4  => 'تیر',
			5  => 'مرداد',
			6  => 'شهریور',
			7  => 'مهر',
			8  => 'آبان',
			9  => 'آذر',
			10 => 'دی',
			11 => 'بهمن',
			12 => 'اسفند',
		];

		$m = intval( $_GET['pwp_m'] ?? 0 );
		echo '<select name="pwp_m" id="filter-by-pdate">';
		echo '<option ' . selected( $m, 0, false ) . ' value="0">همه تاریخ‌ها</option>';

		foreach ( $dates as $year => $months ) {

			printf( '<optgroup label="سال %s">', CalendarUtils::convertNumbers( $year ) );

			foreach ( $months as $month => $count ) {

				printf( '<option %s value="%d">%s %s (%s)</option>',
					selected( $year . zeroise( $month, 2 ), $m, true ),
					$year . zeroise( $month, 2 ),
					$month_names[ $month ],
					CalendarUtils::convertNumbers( $year ),
					CalendarUtils::convertNumbers( $count )
				);

			}

			printf( '</optgroup>' );
		}

		echo '</select>';
		?>
		<style>
            #filter-by-date {
                display: none;
            }
		</style>
		<?php
	}

	public function date_i18n( $date, $format, $timestamp, $gmt ) {

		$timezone = get_option( 'timezone_string', 'Asia/Tehran' );

		if ( empty( $timezone ) ) {
			$timezone = 'Asia/Tehran';
		}

		$timezone = new \DateTimeZone( $timezone );

		return $this->wp_date( $date, $format, $timestamp, $timezone );
	}

	public function wp_date( $date, $format, $timestamp, $timezone ) {

		$format = str_replace( 'M', 'F', $format );

		try {
			return Jalalian::fromDateTime( $timestamp, $timezone )->format( $format );
		} catch ( Exception $e ) {
			return $date;
		}
	}

	/**
	 * Convert numbers to english numbers
	 *
	 * @param int|string $number
	 *
	 * @return mixed
	 */
	private static function en( $number ) {
		return str_replace( [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ], range( 0, 9 ), $number );
	}
}

new Persian_Woocommerce_Date();
