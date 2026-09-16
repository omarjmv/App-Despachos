import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path_provider/path_provider.dart';
import 'package:signature/signature.dart';
import 'package:uuid/uuid.dart';

import '../../../core/error/failure.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../routes/data/routes_repository.dart';
import '../../routes/domain/route_stop.dart';
import '../data/delivery_coordinator.dart';
import '../domain/delivery.dart';

class _ItemDraft {
  _ItemDraft(this.info) : delivered = TextEditingController(text: info.quantityDispatched.toStringAsFixed(0));

  final DispatchItemInfo info;
  final TextEditingController delivered;
  String? rejectionReason;

  double get deliveredQty => double.tryParse(delivered.text) ?? 0;
  double get rejectedQty => (info.quantityDispatched - deliveredQty).clamp(0, info.quantityDispatched);
}

class DeliveryScreen extends ConsumerStatefulWidget {
  const DeliveryScreen({required this.stopId, super.key});

  final int stopId;

  @override
  ConsumerState<DeliveryScreen> createState() => _DeliveryScreenState();
}

class _DeliveryScreenState extends ConsumerState<DeliveryScreen> {
  final _signatureController = SignatureController(penStrokeWidth: 3, penColor: Colors.black);
  final _notesController = TextEditingController();
  final _picker = ImagePicker();

  List<_ItemDraft>? _drafts;
  RouteStop? _stop;
  File? _photo;
  bool _submitting = false;

  @override
  void dispose() {
    _signatureController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  void _initDrafts(RouteStop stop) {
    _stop = stop;
    _drafts ??= stop.dispatch.items.map(_ItemDraft.new).toList();
  }

  Future<void> _takePhoto() async {
    final photo = await _picker.pickImage(source: ImageSource.camera, imageQuality: 70, maxWidth: 1600);
    if (photo != null) {
      setState(() => _photo = File(photo.path));
    }
  }

  Future<Position?> _currentPosition() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) return null;

      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
        return null;
      }

      return await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 10)),
      );
    } catch (_) {
      return null;
    }
  }

  Future<void> _submit() async {
    final drafts = _drafts;
    if (drafts == null) return;

    if (_signatureController.isEmpty) {
      ScaffoldMessenger.of(context)
          .showSnackBar(const SnackBar(content: Text('Se requiere la firma del cliente para finalizar.')));
      return;
    }

    // Cuando lo entregado es menor a lo despachado, se exige un motivo,
    // igual que en preparación (sección 12 del brief).
    for (final draft in drafts) {
      if (draft.rejectedQty > 0 && (draft.rejectionReason == null || draft.rejectionReason!.isEmpty)) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Indique el motivo de rechazo para ${draft.info.product.name}.')),
        );
        return;
      }
    }

    setState(() => _submitting = true);

    try {
      final position = await _currentPosition();
      final signatureFile = await _persistSignature();

      final result = await ref.read(deliveryCoordinatorProvider).submit(
            stopId: widget.stopId,
            customerName: _stop!.dispatch.customer.name,
            clientUuid: const Uuid().v4(),
            items: drafts
                .map((d) => DeliveryItemPayload(
                      dispatchItemId: d.info.id,
                      quantityDelivered: d.deliveredQty,
                      quantityRejected: d.rejectedQty,
                      rejectionReason: d.rejectedQty > 0 ? d.rejectionReason : null,
                    ))
                .toList(),
            notes: _notesController.text.trim().isEmpty ? null : _notesController.text.trim(),
            latitude: position?.latitude,
            longitude: position?.longitude,
            signatureFile: signatureFile,
            photoFile: _photo,
          );

      ref.invalidate(myRouteProvider);
      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            result.queuedOffline
                ? 'Sin conexión: la entrega se guardó en el dispositivo y se enviará automáticamente.'
                : 'Entrega registrada: ${result.delivery!.result}',
          ),
        ),
      );
      context.pop();
    } catch (e) {
      if (!mounted) return;
      final message = errorMessageOf(e);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<File?> _persistSignature() async {
    final signatureBytes = await _signatureController.toPngBytes();
    if (signatureBytes == null) return null;

    final tempDir = await getTemporaryDirectory();
    final file = File('${tempDir.path}/firma_${DateTime.now().millisecondsSinceEpoch}.png');
    return file.writeAsBytes(signatureBytes);
  }

  @override
  Widget build(BuildContext context) {
    final routeAsync = ref.watch(myRouteProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Registrar entrega')),
      body: AsyncValueView(
        value: routeAsync,
        onRetry: () => ref.invalidate(myRouteProvider),
        builder: (context, route) {
          RouteStop? stop;
          for (final s in route?.stops ?? const <RouteStop>[]) {
            if (s.id == widget.stopId) {
              stop = s;
              break;
            }
          }
          if (stop == null) {
            return const Center(child: Text('No se encontró esta parada.'));
          }

          _initDrafts(stop);

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(stop.dispatch.customer.name, style: Theme.of(context).textTheme.titleLarge),
              Text('Despacho ${stop.dispatch.number}'),
              const SizedBox(height: 16),
              ..._drafts!.map((draft) => Card(
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(draft.info.product.name, style: Theme.of(context).textTheme.titleMedium),
                          Text('Despachado: ${draft.info.quantityDispatched}'),
                          const SizedBox(height: 8),
                          TextField(
                            controller: draft.delivered,
                            keyboardType: TextInputType.number,
                            decoration: const InputDecoration(labelText: 'Cantidad entregada'),
                            onChanged: (_) => setState(() {}),
                          ),
                          if (draft.rejectedQty > 0) ...[
                            const SizedBox(height: 8),
                            Text(
                              'Rechazado: ${draft.rejectedQty}',
                              style: const TextStyle(color: Colors.orange, fontWeight: FontWeight.bold),
                            ),
                            DropdownButtonFormField<String>(
                              initialValue: draft.rejectionReason,
                              items: const [
                                'PRODUCTO_DANADO', 'CLIENTE_RECHAZO', 'ERROR_PEDIDO', 'OTRO',
                              ].map((r) => DropdownMenuItem(value: r, child: Text(r))).toList(),
                              onChanged: (v) => setState(() => draft.rejectionReason = v),
                              decoration: const InputDecoration(labelText: 'Motivo de rechazo'),
                            ),
                          ],
                        ],
                      ),
                    ),
                  )),
              const SizedBox(height: 16),
              Text('Firma del cliente', style: Theme.of(context).textTheme.titleMedium),
              Container(
                decoration: BoxDecoration(border: Border.all(color: Colors.grey.shade400)),
                height: 180,
                child: Signature(controller: _signatureController, backgroundColor: Colors.white),
              ),
              TextButton(
                onPressed: () => setState(() => _signatureController.clear()),
                child: const Text('Limpiar firma'),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  OutlinedButton.icon(
                    onPressed: _takePhoto,
                    icon: const Icon(Icons.camera_alt_outlined),
                    label: Text(_photo == null ? 'Tomar fotografía' : 'Foto capturada'),
                  ),
                  if (_photo != null) ...[
                    const SizedBox(width: 12),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: Image.file(_photo!, height: 48, width: 48, fit: BoxFit.cover),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _notesController,
                maxLines: 2,
                decoration: const InputDecoration(labelText: 'Observaciones'),
              ),
              const SizedBox(height: 24),
              ElevatedButton.icon(
                onPressed: _submitting ? null : _submit,
                icon: _submitting
                    ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.check_circle_outline),
                label: const Text('Finalizar entrega'),
              ),
            ],
          );
        },
      ),
    );
  }
}
