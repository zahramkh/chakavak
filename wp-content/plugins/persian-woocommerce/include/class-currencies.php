<?php

defined( 'ABSPATH' ) || exit;

class Persian_Woocommerce_Currencies extends Persian_Woocommerce_Core {

	/** @var array */
	public $currencies;

	public function __construct() {

		$this->currencies = [
			'IRR'  => __( 'ریال', 'woocommerce' ),
			'IRHR' => __( 'هزار ریال', 'woocommerce' ),
			'IRT'  => __( 'تومان', 'woocommerce' ),
			'IRHT' => __( 'هزار تومان', 'woocommerce' ),
		];

		add_filter( 'woocommerce_currencies', [ $this, 'currencies' ] );
		add_filter( 'woocommerce_currency_symbol', [ $this, 'currency_symbol' ], 10, 2 );
		add_filter( 'woocommerce_structured_data_product_offer', [ $this, 'fix_prices_in_structured_data' ] );
	}

	public function currencies( array $currencies ): array {
		return $this->currencies + $currencies;
	}

	public function currency_symbol( $currency_symbol, $currency ) {
		return $this->currencies[ $currency ] ?? $currency_symbol;
	}

	public function fix_prices_in_structured_data( array $markup_offer ): array {

		$currency = get_woocommerce_currency();

		foreach ( $markup_offer as $key => &$value ) {

			if ( $key == 'priceCurrency' ) {
				$value = 'IRR';
			}

			if ( in_array( $key, [ 'price', 'lowPrice', 'highPrice' ] ) ) {

				if ( $currency == 'IRT' ) {
					$value *= 10;
				}

				if ( $currency == 'IRHR' ) {
					$value *= 1000;
				}

				if ( $currency == 'IRHT' ) {
					$value *= 10000;
				}

			}

			if ( is_array( $value ) ) {
				$value = $this->fix_prices_in_structured_data( $value );
			}

		}

		return $markup_offer;
	}
}

new Persian_Woocommerce_Currencies();