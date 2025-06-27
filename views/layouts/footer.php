    </div> <!-- End of main-content -->
    <div class="main-content">
<!-- Confirmation Modal -->
  <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="confirmModalLabel">Confirm Action</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="confirmModalBody">
          Are you sure you want to proceed?
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" id="confirmModalYes">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>
    <!-- Footer -->
    <footer class="footer bg-dark text-light py-3 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <small>&copy; <?= date('Y') ?> Scotland Yard Detective Game. All rights reserved.</small>
                </div>
                <div class="col-md-6 text-end">
                    <small>
                        <a href="#" class="text-light text-decoration-none" data-bs-toggle="modal" data-bs-target="#helpModal">Help</a> |
                        <a href="#" class="text-light text-decoration-none" data-bs-toggle="modal" data-bs-target="#aboutModal">About</a>
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="helpModalLabel">How to Play Scotland Yard</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>Objective</h6>
                    <p><strong>Detectives:</strong> Work together to catch Mr. X<br>
                    <strong>Mr. X:</strong> Evade capture for 24 rounds</p>
                    
                    <h6>Transportation</h6>
                    <ul>
                        <li><strong>T:</strong> Taxi (11 tickets)</li>
                        <li><strong>B:</strong> Bus (8 tickets)</li>
                        <li><strong>U:</strong> Underground (4 tickets)</li>
                        <li><strong>X:</strong> Hidden moves (5 tickets, Mr. X only)</li>
                        <li><strong>2:</strong> Double moves (2 tickets, Mr. X only)</li>
                    </ul>
                    
                    <h6>Special Rules</h6>
                    <ul>
                        <li>Mr. X's position is revealed on rounds 3, 8, 13, 18, 23, 28, 33, 38</li>
                        <li>Mr. X uses QR codes to keep moves secret</li>
                        <li>Detectives can see each other's positions</li>
                        <li>Mr. X wins if he evades capture for 24 rounds</li>
                        <li>Detectives win if they catch Mr. X</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- About Modal -->
    <div class="modal fade" id="aboutModal" tabindex="-1" aria-labelledby="aboutModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="aboutModalLabel">About Scotland Yard</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Scotland Yard is a classic detective board game where players work together to catch the elusive Mr. X as he moves around London.</p>
                    <p>This is a PHP-based web implementation of the original game, featuring:</p>
                    <ul>
                        <li>Multi-player support</li>
                        <li>Real-time updates</li>
                        <li>AI detectives</li>
                        <li>QR code integration for Mr. X's moves</li>
                        <li>Interactive London map</li>
                    </ul>
                    <p><strong>Version:</strong> 1.0<br>
                    <strong>Developed with:</strong> PHP, MySQL, Bootstrap, JavaScript</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    

    <!-- Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if (isset($includeCustomJS) && $includeCustomJS): ?>
    <?= $includeCustomJS ?>
    <?php endif; ?>
</body>
</html> 