<?php
session_start();
require_once "includes/json_data.php";

$successMessage = "";
$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $dateDebut = $_POST['date_debut'] ?? '';
    $dateFin = $_POST['date_fin'] ?? '';
    $nbPersonnes = $_POST['nb_personnes'] ?? '';
    $typeChambre = $_POST['type_chambre'] ?? '';
    $activites = $_POST['activites'] ?? [];
    $message = trim($_POST['message'] ?? '');

    if (!$nom || !$email || !$dateDebut || !$dateFin || !$nbPersonnes || !$typeChambre) {
        $errorMessage = "Veuillez remplir tous les champs obligatoires.";
    } else {
        $reservations = readJson("reservation.json") ?: [];

        $id = generateId();

        $nouvelleReservation = [
            "id" => $id,
            "nom" => $nom,
            "email" => $email,
            "date_debut" => $dateDebut,
            "date_fin" => $dateFin,
            "nb_personnes" => $nbPersonnes,
            "type_chambre" => $typeChambre,
            "activites" => $activites,
            "message" => $message,
            "statut" => "en_attente",
            "date_creation" => date("Y-m-d H:i:s")
        ];

        $reservations[] = $nouvelleReservation;

        writeJson("reservation.json", $reservations);

        $successMessage = "Votre demande de réservation a été envoyée. L’administrateur la validera prochainement.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <title>Réservation - Paradise Island</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,500,600,700" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Playfair+Display:400,400i,700,700i" rel="stylesheet">

  <link rel="stylesheet" href="css/open-iconic-bootstrap.min.css">
  <link rel="stylesheet" href="css/animate.css">
  <link rel="stylesheet" href="css/owl.carousel.min.css">
  <link rel="stylesheet" href="css/owl.theme.default.min.css">
  <link rel="stylesheet" href="css/magnific-popup.css">
  <link rel="stylesheet" href="css/aos.css">
  <link rel="stylesheet" href="css/ionicons.min.css">
  <link rel="stylesheet" href="css/bootstrap-datepicker.css">
  <link rel="stylesheet" href="css/jquery.timepicker.css">
  <link rel="stylesheet" href="css/flaticon.css">
  <link rel="stylesheet" href="css/icomoon.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .reservation-section {
      padding: 80px 0;
      background: #f8f9fa;
    }

    .reservation-card {
      background: #fff;
      border-radius: 20px;
      padding: 40px;
      box-shadow: 0 15px 35px rgba(0,0,0,0.08);
    }

    .reservation-card h2 {
      margin-bottom: 10px;
    }

    .reservation-card .form-control,
    .reservation-card .form-select,
    .reservation-card textarea {
      border-radius: 12px;
      min-height: 50px;
    }

    .reservation-card textarea {
      min-height: 120px;
      resize: vertical;
    }

    .checkbox-group {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 12px;
      margin-top: 10px;
    }

    .checkbox-item {
      background: #f8f9fa;
      border-radius: 12px;
      padding: 12px 15px;
      border: 1px solid #e9ecef;
    }

    .hero-wrap.hero-wrap-2 {
      height: 500px !important;
      position: relative;
      background-size: cover;
      background-position: center;
    }

    .hero-wrap.hero-wrap-2 .overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.35);
    }

    .hero-wrap.hero-wrap-2 .slider-text {
      height: 500px !important;
    }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark ftco_navbar bg-dark ftco-navbar-light" id="ftco-navbar">
  <div class="container">
    <a class="navbar-brand" href="index.php">Paradise Island</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#ftco-nav" aria-controls="ftco-nav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="oi oi-menu"></span> Menu
    </button>

    <div class="collapse navbar-collapse" id="ftco-nav">
      <ul class="navbar-nav ml-auto">
        <li class="nav-item"><a href="index.php" class="nav-link">Accueil</a></li>
        <li class="nav-item"><a href="activites.php" class="nav-link">Activités</a></li>
        <li class="nav-item active"><a href="reservation.php" class="nav-link">Réserver</a></li>
        <li class="nav-item"><a href="Connexion.php" class="nav-link">Connexion</a></li>
      </ul>
    </div>
  </div>
</nav>

<section class="hero-wrap hero-wrap-2" style="background-image: url('images/ile4.jpg');">
  <div class="overlay"></div>
  <div class="container">
    <div class="row no-gutters slider-text align-items-end justify-content-center">
      <div class="col-md-9 ftco-animate pb-5 text-center">
        <h1 class="mb-3 bread text-white">Demande de réservation</h1>
        <p class="text-white">Préparez votre séjour de rêve sur notre île privée.</p>
      </div>
    </div>
  </div>
</section>

<section class="reservation-section">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <div class="reservation-card">

          <div class="text-center mb-4">
            <h2>Réservez votre séjour</h2>
            <p>Remplissez ce formulaire pour envoyer votre demande à l’administrateur.</p>
          </div>

          <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success">
              <?php echo htmlspecialchars($successMessage); ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger">
              <?php echo htmlspecialchars($errorMessage); ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="reservation.php">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="nom">Nom complet *</label>
                <input type="text" class="form-control" id="nom" name="nom" required value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
              </div>

              <div class="col-md-6 mb-3">
                <label for="email">Adresse email *</label>
                <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
              </div>

              <div class="col-md-6 mb-3">
                <label for="date_debut">Date de début *</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" required value="<?php echo htmlspecialchars($_POST['date_debut'] ?? ''); ?>">
              </div>

              <div class="col-md-6 mb-3">
                <label for="date_fin">Date de fin *</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" required value="<?php echo htmlspecialchars($_POST['date_fin'] ?? ''); ?>">
              </div>

              <div class="col-md-6 mb-3">
                <label for="nb_personnes">Nombre de personnes *</label>
                <select class="form-control" id="nb_personnes" name="nb_personnes" required>
                  <option value="">Choisir...</option>
                  <?php for ($i = 1; $i <= 6; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo (($_POST['nb_personnes'] ?? '') == $i) ? 'selected' : ''; ?>>
                      <?php echo $i; ?> <?php echo $i === 1 ? 'personne' : 'personnes'; ?>
                    </option>
                  <?php endfor; ?>
                </select>
              </div>

              <div class="col-md-6 mb-3">
                <label for="type_chambre">Type de chambre *</label>
                <select class="form-control" id="type_chambre" name="type_chambre" required>
                  <option value="">Choisir...</option>
                  <option value="Bungalow sur pilotis" <?php echo (($_POST['type_chambre'] ?? '') === 'Bungalow sur pilotis') ? 'selected' : ''; ?>>Bungalow sur pilotis</option>
                  <option value="Villa sur la plage" <?php echo (($_POST['type_chambre'] ?? '') === 'Villa sur la plage') ? 'selected' : ''; ?>>Villa sur la plage</option>
                  <option value="Suite avec piscine privée" <?php echo (($_POST['type_chambre'] ?? '') === 'Suite avec piscine privée') ? 'selected' : ''; ?>>Suite avec piscine privée</option>
                </select>
              </div>

              <div class="col-12 mb-3">
                <label>Activités souhaitées</label>
                <div class="checkbox-group">
                  <?php
                  $selectedActivities = $_POST['activites'] ?? [];
                  $listeActivites = [
                    "Plongée sous-marine",
                    "Sortie en bateau",
                    "Paddle / Kayak",
                    "Yoga au lever du soleil",
                    "Observation des tortues",
                    "Tennis tropical"
                  ];
                  foreach ($listeActivites as $activite):
                  ?>
                    <label class="checkbox-item">
                      <input
                        type="checkbox"
                        name="activites[]"
                        value="<?php echo htmlspecialchars($activite); ?>"
                        <?php echo in_array($activite, $selectedActivities) ? 'checked' : ''; ?>
                      >
                      <?php echo htmlspecialchars($activite); ?>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="col-12 mb-4">
                <label for="message">Message</label>
                <textarea class="form-control" id="message" name="message" placeholder="Précisez vos souhaits, préférences ou informations complémentaires..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
              </div>

              <div class="col-12 text-center">
                <button type="submit" class="btn btn-primary py-3 px-5">
                  Envoyer ma demande
                </button>
              </div>
            </div>
          </form>

        </div>
      </div>
    </div>
  </div>
</section>

<footer class="ftco-footer ftco-bg-dark ftco-section">
  <div class="container">
    <div class="row mb-5">
      <div class="col-md">
        <div class="ftco-footer-widget mb-4">
          <h2 class="ftco-heading-2">Paradise Island</h2>
          <p>
            Paradise Island est un hôtel de luxe situé sur une île privée tropicale,
            offrant un séjour unique entre confort, élégance et activités d’exception.
          </p>
        </div>
      </div>

      <div class="col-md">
        <div class="ftco-footer-widget mb-4 ml-md-5">
          <h2 class="ftco-heading-2">Liens utiles</h2>
          <ul class="list-unstyled">
            <li><a href="index.php" class="py-2 d-block">Accueil</a></li>
            <li><a href="activites.php" class="py-2 d-block">Activités</a></li>
            <li><a href="reservation.php" class="py-2 d-block">Réserver</a></li>
            <li><a href="Connexion.php" class="py-2 d-block">Connexion</a></li>
          </ul>
        </div>
      </div>

      <div class="col-md">
        <div class="ftco-footer-widget mb-4">
          <h2 class="ftco-heading-2">Contact</h2>
          <ul>
            <li><span class="icon icon-map-marker"></span><span class="text">Île privée Paradise Island, Océan Tropical</span></li>
            <li><a href="#"><span class="icon icon-phone"></span><span class="text">+216 70 000 000</span></a></li>
            <li><a href="#"><span class="icon icon-envelope"></span><span class="text">contact@paradise-island.com</span></a></li>
          </ul>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-12 text-center">
        <p>Copyright &copy;<script>document.write(new Date().getFullYear());</script> Paradise Island</p>
      </div>
    </div>
  </div>
</footer>

<script src="js/jquery.min.js"></script>
<script src="js/jquery-migrate-3.0.1.min.js"></script>
<script src="js/popper.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.easing.1.3.js"></script>
<script src="js/jquery.waypoints.min.js"></script>
<script src="js/jquery.stellar.min.js"></script>
<script src="js/owl.carousel.min.js"></script>
<script src="js/jquery.magnific-popup.min.js"></script>
<script src="js/aos.js"></script>
<script src="js/jquery.animateNumber.min.js"></script>
<script src="js/bootstrap-datepicker.js"></script>
<script src="js/jquery.timepicker.min.js"></script>
<script src="js/scrollax.min.js"></script>
<script src="js/main.js"></script>

</body>
</html>