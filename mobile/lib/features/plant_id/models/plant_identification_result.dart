/// Data model for Pl@ntNet API identification result
class PlantIdentificationResult {
  final String bestMatch;
  final List<SpeciesResult> results;
  final int remainingRequests;
  final List<String> predictedOrgans;

  PlantIdentificationResult({
    required this.bestMatch,
    required this.results,
    required this.remainingRequests,
    required this.predictedOrgans,
  });

  factory PlantIdentificationResult.fromJson(Map<String, dynamic> json) {
    // Parse results array
    final resultsList = <SpeciesResult>[];
    final resultsJson = json['results'];
    if (resultsJson is List) {
      for (final result in resultsJson) {
        if (result is Map<String, dynamic>) {
          resultsList.add(SpeciesResult.fromJson(result));
        }
      }
    }

    // Parse predicted organs
    final predictedOrgansList = <String>[];
    final predictedOrgansJson = json['predictedOrgans'];
    if (predictedOrgansJson is List) {
      for (final organ in predictedOrgansJson) {
        if (organ is Map<String, dynamic>) {
          final organName = organ['organ'];
          if (organName is String) {
            predictedOrgansList.add(organName);
          }
        }
      }
    }

    return PlantIdentificationResult(
      bestMatch: json['bestMatch'] as String? ?? '',
      results: resultsList,
      remainingRequests: json['remainingIdentificationRequests'] as int? ?? 0,
      predictedOrgans: predictedOrgansList,
    );
  }
}

/// Individual species result from Pl@ntNet API
class SpeciesResult {
  final double score;
  final String scientificName;
  final List<String> commonNames;
  final String family;
  final String genus;

  SpeciesResult({
    required this.score,
    required this.scientificName,
    required this.commonNames,
    required this.family,
    required this.genus,
  });

  factory SpeciesResult.fromJson(Map<String, dynamic> json) {
    // Parse common names
    final commonNamesList = <String>[];
    final speciesJson = json['species'];
    if (speciesJson is Map<String, dynamic>) {
      final commonNamesJson = speciesJson['commonNames'];
      if (commonNamesJson is List) {
        for (final name in commonNamesJson) {
          if (name is String) {
            commonNamesList.add(name);
          } else if (name is Map<String, dynamic>) {
            final nameValue = name['name'];
            if (nameValue is String) {
              commonNamesList.add(nameValue);
            }
          }
        }
      }
    }

    // Get scientific name without author
    String scientificName = '';
    if (speciesJson is Map<String, dynamic>) {
      final scientificNameValue = speciesJson['scientificNameWithoutAuthor'];
      if (scientificNameValue is String) {
        scientificName = scientificNameValue;
      }
    }

    // Get family
    String family = '';
    if (speciesJson is Map<String, dynamic>) {
      final familyJson = speciesJson['family'];
      if (familyJson is Map<String, dynamic>) {
        final familyValue = familyJson['scientificNameWithoutAuthor'];
        if (familyValue is String) {
          family = familyValue;
        }
      }
    }

    // Get genus
    String genus = '';
    if (speciesJson is Map<String, dynamic>) {
      final genusJson = speciesJson['genus'];
      if (genusJson is Map<String, dynamic>) {
        final genusValue = genusJson['scientificNameWithoutAuthor'];
        if (genusValue is String) {
          genus = genusValue;
        }
      }
    }

    // Get score
    double score = 0.0;
    final scoreValue = json['score'];
    if (scoreValue is num) {
      score = scoreValue.toDouble();
    }

    return SpeciesResult(
      score: score,
      scientificName: scientificName,
      commonNames: commonNamesList,
      family: family,
      genus: genus,
    );
  }
}
