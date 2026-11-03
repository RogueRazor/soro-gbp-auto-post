<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGBP_Language {

	public static function detect( $post_id ) {
		$lang = self::detect_via_multilingual_plugins( $post_id );
		if ( $lang ) {
			return $lang;
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		return self::detect_by_content( $post->post_title . "\n" . wp_strip_all_tags( $post->post_content ) );
	}

	private static function detect_via_multilingual_plugins( $post_id ) {
		if ( function_exists( 'pll_get_post_language' ) ) {
			$lang = pll_get_post_language( $post_id, 'slug' );
			if ( $lang ) {
				return strtolower( $lang );
			}
		}
		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			$lang_details = apply_filters( 'wpml_post_language_details', false, $post_id );
			if ( is_array( $lang_details ) && ! empty( $lang_details['language_code'] ) ) {
				return strtolower( $lang_details['language_code'] );
			}
		}
		$lang = get_post_meta( $post_id, '_pods_language', true );
		if ( $lang ) {
			return strtolower( $lang );
		}
		return '';
	}

	private static function detect_by_content( $text ) {
		$words   = array_filter( preg_split( '/[\s,.!?;:()"\']+/u', mb_strtolower( $text ) ) );
		$samples = array_slice( array_values( $words ), 0, 120 );
		if ( empty( $samples ) ) {
			return '';
		}
		$scores = array();
		foreach ( self::stopwords() as $lang => $list ) {
			$list = array_map( 'mb_strtolower', $list );
			$scores[ $lang ] = 0;
			foreach ( $samples as $word ) {
				if ( in_array( $word, $list, true ) ) {
					$scores[ $lang ]++;
				}
			}
		}
		arsort( $scores );
		$best = key( $scores );
		if ( ! $best || $scores[ $best ] < 2 ) {
			return '';
		}
		return $best;
	}

	public static function available_languages() {
		$languages = array(
			'en' => __( 'English', 'soro-gbp-auto-post' ),
			'de' => __( 'German', 'soro-gbp-auto-post' ),
			'fr' => __( 'French', 'soro-gbp-auto-post' ),
			'es' => __( 'Spanish', 'soro-gbp-auto-post' ),
			'it' => __( 'Italian', 'soro-gbp-auto-post' ),
			'nl' => __( 'Dutch', 'soro-gbp-auto-post' ),
			'pt' => __( 'Portuguese', 'soro-gbp-auto-post' ),
			'da' => __( 'Danish', 'soro-gbp-auto-post' ),
			'sv' => __( 'Swedish', 'soro-gbp-auto-post' ),
			'no' => __( 'Norwegian', 'soro-gbp-auto-post' ),
			'pl' => __( 'Polish', 'soro-gbp-auto-post' ),
			'tr' => __( 'Turkish', 'soro-gbp-auto-post' ),
		);
		$pl_locales = function_exists( 'pll_languages_list' ) ? pll_languages_list( array( 'fields' => 'slug' ) ) : array();
		foreach ( $pl_locales as $slug ) {
			$slug = strtolower( $slug );
			if ( ! isset( $languages[ $slug ] ) ) {
				$languages[ $slug ] = strtoupper( $slug );
			}
		}
		return apply_filters( 'sgbp_available_languages', $languages );
	}

	private static function stopwords() {
		return array(
			'en' => array( 'the', 'and', 'of', 'to', 'in', 'is', 'you', 'that', 'it', 'for', 'are', 'with', 'this', 'have', 'from', 'your', 'was', 'we', 'will', 'can', 'our', 'at', 'be', 'as', 'on', 'or', 'an', 'not', 'what', 'when', 'how' ),
			'de' => array( 'der', 'die', 'das', 'und', 'ist', 'Sie', 'ich', 'nicht', 'mit', 'sich', 'auf', 'für', 'von', 'dem', 'den', 'ein', 'eine', 'im', 'zu', 'des', 'auch', 'als', 'wie', 'wir', 'werden', 'kann', 'sind', 'bei', 'aus', 'durch', 'ihre', 'haben' ),
			'fr' => array( 'le', 'la', 'les', 'et', 'est', 'en', 'que', 'qui', 'dans', 'pour', 'pas', 'vous', 'une', 'sur', 'avec', 'plus', 'ce', 'il', 'ne', 'au', 'ce', 'sont', 'nous', 'mais', 'par', 'votre' ),
			'es' => array( 'el', 'la', 'los', 'las', 'y', 'en', 'que', 'de', 'un', 'una', 'por', 'con', 'para', 'como', 'más', 'pero', 'sus', 'le', 'ya', 'o', 'este', 'si', 'porque', 'esta', 'cuando', 'muy', 'sin', 'sobre', 'también' ),
			'it' => array( 'il', 'la', 'che', 'di', 'in', 'un', 'una', 'per', 'con', 'del', 'gli', 'le', 'sono', 'non', 'anche', 'come', 'più', 'questa', 'quando', 'molto', 'dalla' ),
			'nl' => array( 'de', 'het', 'een', 'en', 'van', 'is', 'dat', 'op', 'voor', 'met', 'niet', 'zijn', 'aan', 'ook', 'maar', 'om', 'je', 'wij', 'onze', 'uw', 'kunnen', 'worden', 'bij', 'uit', 'als' ),
			'pt' => array( 'de', 'que', 'o', 'e', 'do', 'da', 'em', 'um', 'para', 'com', 'não', 'uma', 'os', 'se', 'por', 'mais', 'como', 'mas', 'dos', 'ao', 'sua', 'nossos', 'também' ),
			'da' => array( 'og', 'er', 'det', 'en', 'til', 'med', 'de', 'for', 'ikke', 'af', 'du', 'den', 'kan', 'vi', 'som', 'på', 'din', 'eller', 'hvordan', 'være' ),
			'sv' => array( 'och', 'att', 'det', 'som', 'en', 'på', 'är', 'för', 'med', 'inte', 'har', 'av', 'om', 'jag', 'kan', 'vi', 'din', 'du', 'eller', 'våra' ),
			'no' => array( 'og', 'er', 'det', 'en', 'til', 'med', 'for', 'ikke', 'har', 'av', 'om', 'jeg', 'kan', 'vi', 'din', 'du', 'eller', 'vår' ),
			'pl' => array( 'nie', 'jest', 'się', 'na', 'że', 'do', 'jak', 'ale', 'czy', 'oraz', 'dla', 'od', 'przez', 'jako', 'tym', 'można', 'nasze', 'nas' ),
			'tr' => array( 've', 'bir', 'bu', 'için', 'ile', 'olarak', 'daha', 'çok', 'gibi', 'nasıl', 'ama', 'olan', 'kadar' ),
		);
	}
}
