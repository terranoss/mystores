class AdConfig {
  AdConfig._();

  /// Whether live ads are enabled via build flag `--dart-define=LIVE_ADS=true`.
  /// Defaults to false so debug and ordinary builds always use Google's published test IDs.
  static const bool isLiveAds = bool.fromEnvironment('LIVE_ADS', defaultValue: false);

  /// Google AdMob official test Ad Unit IDs (Android)
  static const String testBannerAdUnitId = 'ca-app-pub-3940256099942544/6300978111';
  static const String testInterstitialAdUnitId = 'ca-app-pub-3940256099942544/1033173712';

  /// Live Ad Unit IDs supplied via build flags
  static const String liveBannerAdUnitId =
      String.fromEnvironment('ADMOB_BANNER_AD_UNIT_ID', defaultValue: '');
  static const String liveInterstitialAdUnitId =
      String.fromEnvironment('ADMOB_INTERSTITIAL_AD_UNIT_ID', defaultValue: '');

  /// Active Banner Ad Unit ID
  static String get bannerAdUnitId {
    if (isLiveAds && liveBannerAdUnitId.isNotEmpty) {
      return liveBannerAdUnitId;
    }
    return testBannerAdUnitId;
  }

  /// Active Interstitial Ad Unit ID
  static String get interstitialAdUnitId {
    if (isLiveAds && liveInterstitialAdUnitId.isNotEmpty) {
      return liveInterstitialAdUnitId;
    }
    return testInterstitialAdUnitId;
  }
}
