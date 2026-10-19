import 'package:flutter/material.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';
import 'ad_config.dart';

/// Manages throttled interstitial ads for bottom-navigation tab changes.
///
/// Rules:
/// - Shown at most once in any 5-minute window.
/// - Never shown on app launch or on the first navigation of a session.
/// - Suppressed when navigation was initiated by tapping a push notification.
/// - Suppressed immediately after capturing a pick-list photo.
/// - Suppressed immediately after confirming a stock or price edit.
/// - Failure to load or show never blocks tab navigation.
class InterstitialAdManager {
  static final InterstitialAdManager instance = InterstitialAdManager._internal();

  InterstitialAdManager._internal();

  InterstitialAd? _interstitialAd;
  bool _isLoading = false;
  DateTime? _lastShownTime;
  bool _isFirstNavigation = true;

  /// Timestamp of recent photo capture or stock/price edit to suppress interstitial
  DateTime? _suppressionTime;

  /// Cooldown window: 5 minutes
  static const Duration cooldown = Duration(minutes: 5);

  /// Cooldown after sensitive user action: 1 minute
  static const Duration actionSuppressionDuration = Duration(minutes: 1);

  /// Marks a sensitive action completed (e.g. pick-list photo capture or stock/price edit)
  void suppressForRecentAction() {
    _suppressionTime = DateTime.now();
  }

  /// Preloads an interstitial ad in the background.
  void preload() {
    if (_interstitialAd != null || _isLoading) return;

    _isLoading = true;
    InterstitialAd.load(
      adUnitId: AdConfig.interstitialAdUnitId,
      request: const AdRequest(),
      adLoadCallback: InterstitialAdLoadCallback(
        onAdLoaded: (ad) {
          _interstitialAd = ad;
          _isLoading = false;
        },
        onAdFailedToLoad: (error) {
          debugPrint('Interstitial failed to load: ${error.message}');
          _interstitialAd = null;
          _isLoading = false;
        },
      ),
    );
  }

  /// Evaluates whether an interstitial should be displayed on bottom nav tab change.
  /// Never blocks or delays navigation.
  void showIfAllowed({bool isPushNavigation = false}) {
    if (isPushNavigation) return;

    // Suppress on first navigation of a session
    if (_isFirstNavigation) {
      _isFirstNavigation = false;
      preload();
      return;
    }

    final now = DateTime.now();

    // Check recent user action suppression (photo capture or stock edit)
    if (_suppressionTime != null &&
        now.difference(_suppressionTime!) < actionSuppressionDuration) {
      return;
    }

    // Check 5-minute cooldown throttle
    if (_lastShownTime != null && now.difference(_lastShownTime!) < cooldown) {
      return;
    }

    if (_interstitialAd != null) {
      final ad = _interstitialAd!;
      _interstitialAd = null;
      _lastShownTime = now;

      ad.fullScreenContentCallback = FullScreenContentCallback(
        onAdDismissedFullScreenContent: (ad) {
          ad.dispose();
          preload();
        },
        onAdFailedToShowFullScreenContent: (ad, error) {
          debugPrint('Interstitial failed to show: ${error.message}');
          ad.dispose();
          preload();
        },
      );

      ad.show();
    } else {
      preload();
    }
  }
}
